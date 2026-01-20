<?php

return [

    /*
     * The disk on which to store added files and derived images by default.
     */
    'disk_name' => env('MEDIA_DISK', 'public'),

    /*
     * The maximum file size of an item in bytes.
     */
    'max_file_size' => 1024 * 1024 * 10, // 10MB

    /*
     * Queue connection & queue name for conversions/responsive images.
     * Leave null to use Laravel defaults.
     */
    'queue_connection_name' => env('MEDIA_QUEUE_CONNECTION', null),
    'queue_name' => env('MEDIA_QUEUE', null),

    /*
     * IMPORTANT:
     * If true, conversions will be queued (need a running queue worker).
     * For your current setup лучше false, чтобы превью/аватар генерились сразу.
     */
    'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', false),

    /*
     * Usually keep false unless you really need after-commit behavior.
     */
    'queue_conversions_after_database_commit' => env('QUEUE_CONVERSIONS_AFTER_DB_COMMIT', false),

    /*
     * The fully qualified class name of the media model.
     */
    'media_model' => Spatie\MediaLibrary\MediaCollections\Models\Media::class,

    /*
     * The fully qualified class name of the media observer.
     */
    'media_observer' => Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver::class,

    /*
     * Keep disabled unless you know you need it / using Pro components.
     */
    'use_default_collection_serialization' => false,

    /*
     * Media Library Pro (guarded).
     * If Pro is not installed, these MUST NOT hard-reference missing classes.
     */
    'temporary_upload_model' => class_exists(\Spatie\MediaLibraryPro\Models\TemporaryUpload::class)
        ? \Spatie\MediaLibraryPro\Models\TemporaryUpload::class
        : null,

    'enable_temporary_uploads_session_affinity' => env('MEDIA_ENABLE_TMP_UPLOADS_SESSION_AFFINITY', false),
    'generate_thumbnails_for_temporary_uploads' => env('MEDIA_GENERATE_TMP_UPLOAD_THUMBS', false),

    /*
     * Naming / paths / removing.
     */
    'file_namer' => Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer::class,
    'path_generator' => Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator::class,
    'file_remover_class' => Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover::class,

    'custom_path_generators' => [
        // Model::class => PathGenerator::class
    ],

    'url_generator' => Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator::class,

    'moves_media_on_update' => false,
    'version_urls' => false,

    /*
     * Image optimization. If you didn't install spatie/laravel-image-optimizer + binaries,
     * you can safely leave it empty to avoid surprises.
     */
    'image_optimizers' => [
        // Можно включить позже, когда точно поставите бинарники jpegoptim/optipng/pngquant и т.п.
    ],

    'image_generators' => [
        Spatie\MediaLibrary\Conversions\ImageGenerators\Image::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Webp::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Avif::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Pdf::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Svg::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Video::class,
    ],

    'temporary_directory_path' => null,

    /*
     * Conversion engine.
     */
    'image_driver' => env('IMAGE_DRIVER', 'gd'),

    /*
     * FFMPEG settings (only if you generate video thumbnails).
     */
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
    'ffmpeg_timeout' => env('FFMPEG_TIMEOUT', 900),
    'ffmpeg_threads' => env('FFMPEG_THREADS', 0),

    'jobs' => [
        'perform_conversions' => Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob::class,
        'generate_responsive_images' => Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob::class,
    ],

    'media_downloader' => Spatie\MediaLibrary\Downloaders\DefaultDownloader::class,
    'media_downloader_ssl' => env('MEDIA_DOWNLOADER_SSL', true),

    'temporary_url_default_lifetime' => env('MEDIA_TEMPORARY_URL_DEFAULT_LIFETIME', 5),

    'remote' => [
        'extra_headers' => [
            'CacheControl' => 'max-age=604800',
        ],
    ],

    /*
     * Responsive images (можно включить позже, сейчас не обязательно для аватара/галереи).
     */
    'responsive_images' => [
        'width_calculator' => Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator::class,
        'use_tiny_placeholders' => true,
        'tiny_placeholder_generator' => Spatie\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\Blurred::class,
    ],

    'enable_vapor_uploads' => env('ENABLE_MEDIA_LIBRARY_VAPOR_UPLOADS', false),

    'default_loading_attribute_value' => null,

    'prefix' => env('MEDIA_PREFIX', ''),

    /*
     * Лучше false, чтобы поведение было предсказуемым (и не “подгружало” медиа неожиданно).
     */
    'force_lazy_loading' => env('FORCE_MEDIA_LIBRARY_LAZY_LOADING', false),
];
