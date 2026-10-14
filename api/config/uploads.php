<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upload disk
    |--------------------------------------------------------------------------
    | Filesystem disk uploaded files are written to. The default `public` disk
    | is exposed through the /storage symlink created by `php artisan
    | storage:link`; point this at an S3-compatible disk in production.
    */
    'disk' => env('UPLOAD_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Upload directory
    |--------------------------------------------------------------------------
    | Folder (relative to the disk root) new uploads are stored under. A
    | date-based sub-path is appended automatically.
    */
    'path' => env('UPLOAD_PATH', 'uploads'),

    /*
    |--------------------------------------------------------------------------
    | Size limit (kilobytes)
    |--------------------------------------------------------------------------
    */
    'max_kilobytes' => (int) env('UPLOAD_MAX_KILOBYTES', 10240),

    /*
    |--------------------------------------------------------------------------
    | Allowed extensions
    |--------------------------------------------------------------------------
    | Validated by Laravel's `mimes` rule against the file's detected type.
    */
    'mimes' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'csv', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'zip',
    ],
];
