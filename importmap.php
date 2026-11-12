<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 *
 * @return array<string, array{    // Import name as key, description of the imported file as value
 *     path: string,               // Logical, relative or absolute path to the file
 *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
 *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
 * }|array{
 *     version: string,            // Version of the remote package
 *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
 *     type?: 'js'|'css'|'json',
 *     entrypoint?: bool,
 * }>
 */
return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@hotwired/turbo' => ['version' => '8.0.23'],
    'tom-select' => ['version' => '2.7.0'],
    '@orchidjs/sifter' => ['version' => '1.1.0'],
    '@orchidjs/unicode-variants' => ['version' => '1.2.2'],
    'tom-select/dist/css/tom-select.default.min.css' => ['version' => '2.7.0', 'type' => 'css'],
    'tom-select/dist/css/tom-select.default.css' => ['version' => '2.7.0', 'type' => 'css'],
    'tom-select/dist/css/tom-select.bootstrap4.css' => ['version' => '2.7.0', 'type' => 'css'],
    'tom-select/dist/css/tom-select.bootstrap5.css' => ['version' => '2.7.0', 'type' => 'css'],
    '@mati365/ckeditor5-symfony' => ['path' => '@mati365/ckeditor5-symfony/index.mjs'],
    'ckeditor5' => ['path' => './assets/vendor/ckeditor5/dist/browser/ckeditor5.js'],
    'ckeditor5/translations/fr.js' => ['path' => './assets/vendor/ckeditor5/dist/translations/fr.js'],
    'bootstrap' => ['version' => '5.3.9'],
    'bootstrap/dist/css/bootstrap.min.css' => ['version' => '5.3.9', 'type' => 'css'],
    '@fortawesome/fontawesome-free/css/all.css' => ['version' => '7.3.1', 'type' => 'css'],
    'sortablejs' => ['version' => '1.15.7'],
    '@popperjs/core' => ['version' => '2.11.8'],
];
