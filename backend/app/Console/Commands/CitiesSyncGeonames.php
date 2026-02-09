<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CitiesSyncGeonames extends Command
{
    protected $signature = 'cities:sync-geonames
        {--cities= : Path to cities500.txt (relative to project root)}
        {--admin1= : Path to admin1CodesASCII.txt}
        {--alt= : Path to alternateNamesV2.txt}
        {--countries=RU,KZ,UZ : Comma-separated country codes}
        {--lang=ru : Language code for city/region names}
        {--min-pop=0 : Minimum population}
        {--batch=1000 : Upsert batch size}
    ';

    protected $description = 'Sync cities from GeoNames (cities500) + admin1 + optional alternateNames';

    public function handle(): int
    {
        $citiesPath = $this->option('cities') ?: 'storage/app/geonames/cities500.txt';
        $admin1Path = $this->option('admin1') ?: 'storage/app/geonames/admin1CodesASCII.txt';
        $altPath    = $this->option('alt'); // optional

        $countries = array_filter(array_map('trim', explode(',', (string) $this->option('countries'))));
        $lang      = trim((string) $this->option('lang')) ?: 'ru';
        $minPop    = (int) $this->option('min-pop');
        $batchSize = max(100, (int) $this->option('batch'));

        $citiesFile = base_path($citiesPath);
        $admin1File = base_path($admin1Path);
        $altFile    = $altPath ? base_path($altPath) : null;

        if (!is_file($citiesFile)) $this->error("cities file not found: $citiesFile");
        if (!is_file($admin1File)) $this->error("admin1 file not found: $admin1File");
        if (!is_file($citiesFile) || !is_file($admin1File)) return self::FAILURE;

        if ($altFile && !is_file($altFile)) {
            $this->warn("alt file not found, skipping: $altFile");
            $altFile = null;
        }

        // 1) admin1: RU.71 => geonameId + ascii name
        [$admin1ToGeo, $admin1ToAscii] = $this->loadAdmin1($admin1File, $countries);
        $admin1GeoIds = array_values($admin1ToGeo);

        // 2) cities500: соберём нужные geoname_id городов (для alt names)
        $cityGeoIds = $this->scanCitiesForCityIds($citiesFile, $countries, $minPop);

        // 3) alternateNames: RU имена регионов и городов (если файл передан)
        $geoToRegionRu = [];
        $geoToCityRu   = [];
        if ($altFile) {
            $geoToRegionRu = $this->loadAltNamesPreferred($altFile, $admin1GeoIds, $lang);
            $geoToCityRu   = $this->loadAltNamesPreferred($altFile, $cityGeoIds, $lang);
        }

        // 4) основной проход: upsert
        $processed = 0;
        $upsertedTotal = 0;
        $buffer = [];

        $fh = fopen($citiesFile, 'r');
        if (!$fh) {
            $this->error("Cannot open cities file: $citiesFile");
            return self::FAILURE;
        }

        while (($line = fgets($fh)) !== false) {
            $processed++;
            $cols = explode("\t", rtrim($line, "\r\n"));

            // GeoNames cities500 columns:
            // 0 geonameid, 1 name, 2 asciiname, 3 alternatenames, 4 lat, 5 lon,
            // 6 featClass, 7 featCode, 8 countryCode, 9 cc2, 10 admin1,
            // 14 population, 17 timezone
            $geonameId   = (int)($cols[0] ?? 0);
            $nameDefault = (string)($cols[1] ?? '');
            $lat         = isset($cols[4]) ? (float)$cols[4] : null;
            $lon         = isset($cols[5]) ? (float)$cols[5] : null;
            $countryCode = (string)($cols[8] ?? '');
            $admin1Code  = (string)($cols[10] ?? '');
            $population  = isset($cols[14]) ? (int)$cols[14] : null;
            $timezone    = (string)($cols[17] ?? '');

            if (!$geonameId || $nameDefault === '' || $countryCode === '') continue;
            if (!in_array($countryCode, $countries, true)) continue;
            if ($population !== null && $population < $minPop) continue;

            $admin1Key = $countryCode . '.' . $admin1Code;
            $admin1Geo = $admin1ToGeo[$admin1Key] ?? null;

            $regionRu       = $admin1Geo ? ($geoToRegionRu[$admin1Geo] ?? null) : null;
            $regionFallback = $admin1Key ? ($admin1ToAscii[$admin1Key] ?? null) : null;
            $region         = $this->normalizeRegionRu($regionRu ?: $regionFallback);

            $cityRu = $geoToCityRu[$geonameId] ?? null;
            $name   = $cityRu ?: $nameDefault;

            $buffer[] = [
                'geoname_id'   => $geonameId,
                'name'         => $name,
                'region'       => $region,
                'country_code' => $countryCode,
                'timezone'     => $timezone !== '' ? $timezone : null,
                'lat'          => $lat,
                'lon'          => $lon,
                'population'   => $population,
                'updated_at'   => now(),
                'created_at'   => now(),
            ];

            if (count($buffer) >= $batchSize) {
                $upsertedTotal += $this->upsertCities($buffer);
                $buffer = [];
                $this->line("Processed: $processed, upserted total: $upsertedTotal");
            }
        }

        fclose($fh);

        if ($buffer) {
            $upsertedTotal += $this->upsertCities($buffer);
        }

        $this->info("DONE. Read lines: $processed, upserted: $upsertedTotal");
        return self::SUCCESS;
    }

    private function loadAdmin1(string $file, array $countries): array
    {
        $admin1ToGeo = [];
        $admin1ToAscii = [];

        $fh = fopen($file, 'r');
        if (!$fh) return [[], []];

        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;

            $cols = explode("\t", $line);
            // 0=code (RU.71), 1=name, 2=asciiname, 3=geonameid
            $code  = $cols[0] ?? null;
            $ascii = $cols[2] ?? null;
            $geoId = isset($cols[3]) ? (int)$cols[3] : null;

            if (!$code || !$geoId) continue;

            [$cc] = explode('.', $code, 2);
            if (!in_array($cc, $countries, true)) continue;

            $admin1ToGeo[$code] = $geoId;
            $admin1ToAscii[$code] = $ascii ?: ($cols[1] ?? null);
        }

        fclose($fh);
        return [$admin1ToGeo, $admin1ToAscii];
    }

    private function scanCitiesForCityIds(string $citiesFile, array $countries, int $minPop): array
    {
        $cityGeoIds = [];

        $fh = fopen($citiesFile, 'r');
        if (!$fh) return [];

        while (($line = fgets($fh)) !== false) {
            $cols = explode("\t", rtrim($line, "\r\n"));
            $geonameId   = (int)($cols[0] ?? 0);
            $countryCode = (string)($cols[8] ?? '');
            $population  = isset($cols[14]) ? (int)$cols[14] : null;

            if (!$geonameId || $countryCode === '') continue;
            if (!in_array($countryCode, $countries, true)) continue;
            if ($population !== null && $population < $minPop) continue;

            $cityGeoIds[$geonameId] = true;
        }

        fclose($fh);
        return array_keys($cityGeoIds);
    }

    private function loadAltNamesPreferred(string $file, array $needGeoIds, string $lang): array
    {
        if (empty($needGeoIds)) return [];

        $need = array_fill_keys(array_map('intval', $needGeoIds), true);
        $geoToName = [];
        $hasPreferred = [];

        $fh = fopen($file, 'r');
        if (!$fh) return [];

        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;

            $cols = explode("\t", $line);
            // alternateNamesV2: 0=altNameId, 1=geonameid, 2=isolanguage, 3=alternate name, 4=isPreferredName, ...
            $geonameId = isset($cols[1]) ? (int)$cols[1] : 0;
            if (!$geonameId || !isset($need[$geonameId])) continue;

            $iso = (string)($cols[2] ?? '');
            if ($iso !== $lang) continue;

            $name = (string)($cols[3] ?? '');
            if ($name === '') continue;

            $isPreferred = (int)($cols[4] ?? 0);

            if ($isPreferred === 1) {
                $geoToName[$geonameId] = $name;
                $hasPreferred[$geonameId] = true;
                continue;
            }

            if (!isset($geoToName[$geonameId]) && !isset($hasPreferred[$geonameId])) {
                $geoToName[$geonameId] = $name;
            }
        }

        fclose($fh);
        return $geoToName;
    }

    private function normalizeRegionRu(?string $region): ?string
    {
        if ($region === null) return null;

        $r = trim($region);
        if ($r === '') return null;

        // Делает "Область"->"область", "Край"->"край" (и т.п.)
        $r = preg_replace('/\s+Область$/u', ' область', $r);
        $r = preg_replace('/\s+Край$/u', ' край', $r);
        $r = preg_replace('/\s+Республика$/u', ' республика', $r);

        return $r;
    }

    private function upsertCities(array $rows): int
    {
        DB::table('cities')->upsert(
            $rows,
            ['geoname_id'],
            ['name', 'region', 'country_code', 'timezone', 'lat', 'lon', 'population', 'updated_at']
        );

        return count($rows);
    }
}
