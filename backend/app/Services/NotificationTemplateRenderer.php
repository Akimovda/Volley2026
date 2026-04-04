<?php

namespace App\Services;

final class NotificationTemplateRenderer
{
    public function render(?string $template, array $data = []): ?string
    {
        if ($template === null) {
            return null;
        }

        $result = $template;

        foreach ($this->normalizeData($data) as $key => $value) {
            $result = str_replace('{' . $key . '}', $value, $result);
        }

        return preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $result);
    }

    public function renderMany(array $templates, array $data = []): array
    {
        $result = [];

        foreach ($templates as $key => $template) {
            $result[$key] = $this->render($template, $data);
        }

        return $result;
    }

    private function normalizeData(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $out[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $out[$key] = trim((string) ($value ?? ''));
            }
        }

        return $out;
    }
}
