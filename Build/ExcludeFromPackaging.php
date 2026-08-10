<?php

declare(strict_types=1);

/**
 * Was nicht ins TER-Archiv gehört.
 *
 * Tailor bringt eine Vorgabeliste mit (Tests, .github, Build, vendor …), die
 * aber nichts von node_modules, den MkDocs-Quellen oder var/ weiß. Ohne diese
 * Ergänzung ist das Release-Archiv gemessen 6,1 MB groß, davon der ganz
 * überwiegende Teil der npm-Baum der Barrierefreiheitstests — ausgeliefert an
 * jede Installation.
 *
 * Wirkt nur, wenn die Umgebungsvariable TYPO3_EXCLUDE_FROM_PACKAGING auf diese
 * Datei zeigt; der Release-Workflow setzt sie. `.gitattributes` hilft hier
 * nicht: Tailor liest es nicht.
 *
 * Die Liste muss die Vorgaben wiederholen — sie ersetzt sie, sie ergänzt sie
 * nicht.
 */
return [
    'directories' => [
        // Vorgaben von typo3/tailor
        '.build',
        '.ddev',
        '.git',
        '.github',
        '.gitlab',
        '.gitlab-ci',
        '.idea',
        '.phive',
        'bin',
        'build',
        'public',
        'tailor-version-artefact',
        'tailor-version-upload',
        'tests',
        'tools',
        'vendor',
        // Ergänzungen dieses Pakets
        'node_modules',
        'var',
        'site',
        // Die MkDocs-Quellen erscheinen gerendert unter docs.netthinks.com.
        // Im Paket genügt das TYPO3-übliche Documentation-Verzeichnis.
        'docs',
    ],
    'files' => [
        // Vorgaben von typo3/tailor
        'CODE_OF_CONDUCT.md',
        'DS_Store',
        'Dockerfile',
        'ExtensionBuilder.json',
        'Makefile',
        'bower.json',
        'codeception.yml',
        'composer.lock',
        'crowdin.yaml',
        'docker-compose.yml',
        'dynamicReturnTypeMeta.json',
        'editorconfig',
        'env',
        'eslintignore',
        'eslintrc.json',
        'gitattributes',
        'gitignore',
        'gitlab-ci.yml',
        'gitmodules',
        'gitreview',
        'package-lock.json',
        'package.json',
        'phive.xml',
        'php-cs-fixer.dist.php',
        'php-cs-fixer.php',
        'php_cs',
        'php_cs.php',
        'phpcs.xml',
        'phpcs.xml.dist',
        'phplint.yml',
        'phpstan-baseline.neon',
        'phpstan.neon',
        'phpstan.neon.dist',
        'phpstorm.meta.php',
        'phpunit.xml',
        'phpunit.xml.dist',
        'prettierrc.json',
        'rector.php',
        'scrutinizer.yml',
        'styleci.yml',
        'stylelint.config.js',
        'stylelintrc',
        'travis.yml',
        'tslint.yaml',
        'tslint.yml',
        'typoscript-lint.yaml',
        'typoscript-lint.yml',
        'typoscriptlint.yaml',
        'typoscriptlint.yml',
        'webpack.config.js',
        'webpack.mix.js',
        'yarn.lock',
        // Ergänzungen dieses Pakets
        'mkdocs.yml',
        'requirements.txt',
        'playwright.config.js',
        'CLAUDE.md',
    ],
];
