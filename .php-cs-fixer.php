<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

$rules = [
    '@Symfony' => true,
    '@Symfony:risky' => true,
    '@PHP71Migration' => true,
    '@PHP71Migration:risky' => true,
    // 'phpdoc_to_param_type' => true,
    // 'phpdoc_to_return_type' => true,
    'phpdoc_types_order' => false,

    // overwrite some Symfony rules
    'braces_position' => [
        'functions_opening_brace' => 'same_line',
        'classes_opening_brace' => 'same_line',
    ],
    'function_declaration' => ['closure_function_spacing' => 'none'],
    'concat_space' => ['spacing' => 'one'],
    'phpdoc_align' => false,
    'yoda_style' => false,

    // additional rules
    'phpdoc_add_missing_param_annotation' => true,
    'phpdoc_order' => true,
    'strict_param' => true,
];

$config = new PhpCsFixer\Config();

return $config
    ->setRules($rules)
    ->setIndent("    ")
    ->setRiskyAllowed(true)
    ->setFinder($finder);
