<?php

// Só o que difere do padrão do Livewire; o restante vem de vendor/livewire/livewire/config/livewire.php.
// A chave abaixo substitui o bloco inteiro do pacote, por isso repete os demais valores padrão.
return [

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        // O painel aceita áudios de até 50 MB (orações e jornadas). O padrão do Livewire é 12 MB e
        // barrava o envio antes da validação do campo. Nginx e PHP precisam aceitar o mesmo tamanho
        // (deploy/oracle-ubuntu: client_max_body_size e upload_max_filesize = 64M).
        'rules' => ['required', 'file', 'max:51200'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        // 50 MB numa conexão de 2 Mbps levam cerca de 3,5 minutos.
        'max_upload_time' => 10,
        'cleanup' => true,
    ],

];
