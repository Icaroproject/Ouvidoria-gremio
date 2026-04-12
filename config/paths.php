<?php
/**
 * Caminhos absolutos centralizados do projeto.
 * Sempre use estas constantes — nunca escreva __DIR__ espalhado pelo código.
 */

// Raiz do projeto (onde fica o index.php)
define('ROOT_PATH',    dirname(__DIR__));

// Pastas principais
define('APP_PATH',     ROOT_PATH . '/app');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('INCLUDES_PATH',ROOT_PATH . '/includes');
define('LIB_PATH',     ROOT_PATH . '/lib');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('ASSETS_PATH',  ROOT_PATH . '/assets');
define('DATABASE_PATH',ROOT_PATH . '/database');

// Subpastas de storage (escrita de arquivos)
define('STORAGE_FOTOS',        STORAGE_PATH . '/fotos/');
define('STORAGE_MANIFESTACOES',STORAGE_PATH . '/manifestacoes/');

// URLs públicas (relativas à raiz web)
define('ASSETS_URL',  'assets');
define('STORAGE_URL', 'storage');
