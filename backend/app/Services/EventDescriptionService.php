<?php

namespace App\Services;

use App\Models\Event;
use Mews\Purifier\Facades\Purifier;

class EventDescriptionService
{
    /**
     * Очистка и нормализация description_html
     */
    public function normalizeDescriptionHtml(array $data): array
    {
        // Чистим только если поле присутствует
        if (!array_key_exists('description_html', $data)) {
            return $data;
        }

        $html = trim((string)($data['description_html'] ?? ''));

        if ($html === '') {
            $data['description_html'] = null;
            return $data;
        }

        /*
        |--------------------------------------------------------------------------
        | Purifier
        |--------------------------------------------------------------------------
        */

        $clean = Purifier::clean($html, 'default');

        /*
        |--------------------------------------------------------------------------
        | Fix target="_blank" links
        |--------------------------------------------------------------------------
        */

        $clean = $this->ensureBlankLinksHaveRel($clean);

        /*
        |--------------------------------------------------------------------------
        | Remove empty HTML
        |--------------------------------------------------------------------------
        */

        $cleanText = trim(strip_tags($clean));

        $data['description_html'] =
            ($cleanText === '' && !str_contains($clean, '<img'))
                ? null
                : $clean;

        return $data;
    }

    /**
     * Добавляет rel="noopener noreferrer" для ссылок target="_blank"
     */
    private function ensureBlankLinksHaveRel(string $html): string
    {
        if (trim($html) === '' || stripos($html, '<a') === false) {
            return $html;
        }

        $prev = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $wrapped =
            '<!doctype html><html><head><meta charset="utf-8"></head><body>' .
            $html .
            '</body></html>';

        $dom->loadHTML($wrapped);

        foreach ($dom->getElementsByTagName('a') as $a) {

            if (strtolower(trim($a->getAttribute('target'))) !== '_blank') {
                continue;
            }

            $rel = strtolower(trim($a->getAttribute('rel')));

            $parts = array_values(array_filter(preg_split('/\s+/', $rel)));

            if (!in_array('noopener', $parts, true)) {
                $parts[] = 'noopener';
            }

            if (!in_array('noreferrer', $parts, true)) {
                $parts[] = 'noreferrer';
            }

            $a->setAttribute(
                'rel',
                trim(implode(' ', array_unique($parts)))
            );
        }

        $body = $dom->getElementsByTagName('body')->item(0);

        $out = '';

        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $out;
    }
    /**
     * Сохраняет описание события
     */

    public function store(Event $event, array $data): void
    {
        if (!array_key_exists('description_html', $data)) {
            return;
        }

        $event->description_html = $data['description_html'];

        // сохраняем без observers
        $event->saveQuietly();
    }
}