<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

$rules = [
    '@Symfony' => true,
    '@Symfony:risky' => true,
    '@PHP71Migration' => true,
    '@PHP71Migration:risky' => true,
    'declare_strict_types' => false,

    // overwrite some Symfony rules
    'braces' => ['position_after_functions_and_oop_constructs' => 'same'],
    'function_declaration' => ['closure_function_spacing' => 'none'],
    'concat_space' => ['spacing' => 'one'],
    'phpdoc_align' => false,
    'yoda_style' => false,

    // additional rules
    'phpdoc_add_missing_param_annotation' => ['only_untyped' => false],
    'phpdoc_order' => true,
    'strict_param' => true,
];

$config = new PhpCsFixer\Config();

return $config
    ->setRules($rules)
    ->setIndent("    ")
    ->setRiskyAllowed(true)
    ->setFinder($finder);
