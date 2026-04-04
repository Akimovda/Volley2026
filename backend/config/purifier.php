<?php
/**
 * Mews Purifier config.
 *
 * IMPORTANT:
 * - Не удаляем default settings.
 * - Профиль `default` используется в \Purifier::clean($html) (если профиль не указан).
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */
return [
    'encoding'         => 'UTF-8',
    'finalize'         => true,
    'ignoreNonStrings' => false,

    // Убедись, что папка существует и доступна на запись:
    // storage/app/purifier
    'cachePath'     => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        /**
         * ✅ Профиль по умолчанию — используем для description_html
         */
        'default' => [
            /**
             * ❗️HTMLPurifier НЕ поддерживает HTML5 doctype.
             * Допустимые: HTML 4.01 Transitional/Strict, XHTML 1.0..., XHTML 1.1
             */
            'HTML.Doctype' => 'HTML 4.01 Transitional',

            /**
             * ✅ Подключаем HTML5 definitions (чтобы purifier не выкидывал figure/figcaption и т.п.)
             * Должно совпадать с settings.custom_definition[id/rev]
             */
            'HTML.DefinitionID'  => 'html5-definitions',
            'HTML.DefinitionRev' => 2,

            // Безопасный набор тегов для описания мероприятия
            'HTML.Allowed' =>
                // контейнеры/текст
                'div,span,p,br,' .
                'b,strong,i,em,u,s,sub,sup,mark,blockquote,' .
                // заголовки
                'h1,h2,h3,h4,h5,h6,' .
                // списки
                'ul,ol,li,' .
                // ссылки
                'a[href|title|target|rel],' .
                // картинки
                'img[src|alt|title|width|height],' .
                // html5 media/вложения (Trix часто рендерит <figure> и т.п.)
                'figure,figcaption,' .
                // таблицы
                'table,thead,tbody,tfoot,tr,td,th,' .
                // iframe (работает только вместе с SafeIframe + Regexp ниже)
                'iframe[src|width|height|frameborder|allowfullscreen]',

            // Разрешённые CSS свойства (минимально необходимые)
            'CSS.AllowedProperties' =>
                'font,font-size,font-weight,font-style,font-family,' .
                'text-decoration,text-align,' .
                'color,background-color,' .
                'padding-left,margin-left',

            // Форматирование
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty'   => true,

            // target для ссылок
            'Attr.AllowedFrameTargets' => ['_blank', '_self', '_top', '_parent'],

            // ✅ Iframe: включаем и ограничиваем домены
            'HTML.SafeIframe'      => true,
            'URI.SafeIframeRegexp' => "%^(https?:)?//(www\\.youtube\\.com/embed/|player\\.vimeo\\.com/video/)%",
        ],

        /**
         * Пример профиля “test”
         */
        'test' => [
            'Attr.EnableID' => true,
        ],

        /**
         * Если хочешь чистить “видео-вставки” отдельно:
         * \Purifier::clean($html, 'youtube')
         */
        'youtube' => [
            'HTML.SafeIframe'      => true,
            'URI.SafeIframeRegexp' => "%^(https?:)?//(www\\.youtube\\.com/embed/|player\\.vimeo\\.com/video/)%",
        ],

        /**
         * HTML5 definitions (для htmlpurifier, чтобы не выкидывал html5-теги)
         */
        'custom_definition' => [
            'id'    => 'html5-definitions',
            'rev'   => 2,
            'debug' => false,
            'elements' => [
                // Sections
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],
                // Grouping
                ['figure',     'Block',  'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
                // Text-level
                ['s',    'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                ['mark', 'Inline', 'Inline', 'Common'],
                ['wbr',  'Inline', 'Empty',  'Core'],
                // Media
                ['video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
                    'src'      => 'URI',
                    'type'     => 'Text',
                    'width'    => 'Length',
                    'height'   => 'Length',
                    'poster'   => 'URI',
                    'preload'  => 'Enum#auto,metadata,none',
                    'controls' => 'Bool',
                ]],
                ['source', 'Block', 'Flow', 'Common', [
                    'src'  => 'URI',
                    'type' => 'Text',
                ]],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['table',  'height',          'Text'],
                ['td',     'border',          'Text'],
                ['th',     'border',          'Text'],
                ['tr',     'width',           'Text'],
                ['tr',     'height',          'Text'],
                ['tr',     'border',          'Text'],
            ],
        ],

        /**
         * Доп. кастом-атрибуты
         */
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_top,_parent'],
            ['a', 'rel',    'Text'],
        ],

        /**
         * Доп. элементы
         */
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],
    ],
];