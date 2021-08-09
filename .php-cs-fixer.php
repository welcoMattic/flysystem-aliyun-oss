<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->append([__FILE__])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP80Migration' => true,
        '@PhpCsFixer' => true,
        'php_unit_internal_class' => false, // From @PhpCsFixer but we don't want it
        'php_unit_test_class_requires_covers' => false, // From @PhpCsFixer but we don't want it
        'phpdoc_add_missing_param_annotation' => false, // From @PhpCsFixer but we don't want it
        'concat_space' => ['spacing' => 'one'],
        'braces' => [
            'position_after_functions_and_oop_constructs' => 'next',
        ],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
            'always_move_variable' => true,
        ],
    ])
    ->setFinder($finder)
;
