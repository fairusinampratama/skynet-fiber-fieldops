<?php

use App\Support\FieldopsPhotoUpload;

return [
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:' . FieldopsPhotoUpload::MAX_UPLOAD_KILOBYTES],
        'max_upload_time' => 10,
    ],
];
