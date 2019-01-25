<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('.php_cs');

$rules = [
    '@Symfony' => true,
    '@Symfony:risky' => true,
    '@PHP56Migration' => true,
    '@PHP56Migration:risky' => true,

    // overwrite some Symfony rules
    'braces' => ['position_after_functions_and_oop_constructs' => 'same'],
    'function_declaration' => ['closure_function_spacing' => 'none'],
    'concat_space' => ['spacing' => 'one'],
    'phpdoc_align' => false,
    'yoda_style' => null,

    // additional rules
    'array_syntax' => ['syntax' => 'short'],
    'is_null' => ['use_yoda_style' => false],
    'modernize_types_casting' => true,
    'ordered_imports' => true,
    'phpdoc_add_missing_param_annotation' => ['only_untyped' => false],
    'phpdoc_order' => true,
    'strict_param' => true,
];

return PhpCsFixer\Config::create()
    ->setRules($rules)
    ->setIndent("    ")
    ->setRiskyAllowed(true)
    ->setFinder($finder);
