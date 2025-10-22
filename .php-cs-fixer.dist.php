<?php

require_once __DIR__ . '/vendor/autoload.php';

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP8x2Migration' => true,
        '@PHP8x0Migration:risky' => true,
        '@PHPUnit10x0Migration:risky' => true,
        '@PER-CS' => true,

        // Set overrides
        'assign_null_coalescing_to_coalesce_equal' => false,
        'use_arrow_functions' => false,
        'declare_strict_types' => true,
        'void_return' => false, // should be enabled after PHPStan can detect types

        // Array Notation
        'trim_array_spaces' => true,
        'whitespace_after_comma_in_array' => [
            'ensure_single_space' => true
        ],
        'no_trailing_comma_in_singleline' => true,

        // String Notation
        'single_quote' => true,

        // Imports
        'fully_qualified_strict_types' => [
            'import_symbols' => true
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true
        ],
        'no_leading_import_slash' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const'
            ],
            'sort_algorithm' => 'alpha'
        ],
        'single_import_per_statement' => [
            'group_to_single_imports' => false
        ],
        'single_line_after_imports' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_between_import_groups' => true,
        'blank_lines_before_namespace' => true,
        'blank_line_after_namespace' => true,

        // PHPUnit
        'php_unit_attributes' => [
            'keep_annotations' => false,
        ],
        'php_unit_construct' => [
            'assertions' => [
                'assertEquals',
                'assertNotEquals',
                'assertNotSame',
                'assertSame',
            ],
        ],
        'php_unit_data_provider_method_order' => [
            'placement' => 'after',
        ],
        'php_unit_data_provider_name' => [
            'prefix' => '',
            'suffix' => 'DataProvider',
        ],
        'php_unit_data_provider_static' => [
            'force' => true,
        ],
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'static',
        ],
        'php_unit_set_up_tear_down_visibility' => true,
        'php_unit_test_annotation' => [
            'style' => 'annotation',
        ],
        'php_unit_method_casing' => [
            'case' => 'camel_case',
        ],
        'php_unit_fqcn_annotation' => true,
        'php_unit_mock_short_will_return' => true,
        'php_unit_strict' => [
            'assertions' => [
                'assertAttributeEquals',
                'assertAttributeNotEquals',
                'assertEquals',
                'assertNotEquals',
            ],
        ],

        // Control structure
        'no_useless_else' => true,
        'no_unneeded_control_parentheses' => [
            'statements' => [
                'break',
                'clone',
                'continue',
                'echo_print',
                'negative_instanceof',
                'others',
                'return',
                'switch_case',
                'yield',
                'yield_from',
            ]
        ],
        'no_unneeded_braces' => [
            'namespaces' => true,
        ],
        'no_superfluous_elseif' => true,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        // Language construct
        'is_null' => true,

        // PHPDoc
        'phpdoc_array_type' => true,
        'no_empty_phpdoc' => true,
        'no_superfluous_phpdoc_tags' => [
            'remove_inheritdoc' => true,
            'allow_mixed' => true // should be disabled after PHPStan can detect types
        ],
        'no_blank_lines_after_phpdoc' => true,
        'phpdoc_order' => [
            'order' => [
                'phpstan-type',
                'phpstan-import-type',
                'property',
                'property-read',
                'property-write',
                'method',
                'mixin',
                'param',
                'return',
                'throws'
            ]
        ],
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_to_comment' => [
            'allow_before_return_statement' => false,
            'ignored_tags' => [
                'var'
            ]
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_indent' => true,
        'phpdoc_align' => [
            'align' => 'left'
        ],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,
        'phpdoc_types' => [
            'groups' => [
                'simple',
                'alias',
                'meta'
            ]
        ],
        'phpdoc_types_order' => [
            'sort_algorithm' => 'none',
            'null_adjustment' => 'always_last'
        ],
        'phpdoc_scalar' => true,
        'phpdoc_param_order' => true,
        'align_multiline_comment' => [
            'comment_type' => 'phpdocs_only'
        ],

        // Return notation
        'no_useless_return' => true,

        // Semicolon
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line'
        ],
        'no_empty_statement' => true,
        'no_singleline_whitespace_before_semicolons' => true,

        // Class organization
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
            'sort_algorithm' => 'none',
        ],

        // Whitespace
        'method_chaining_indentation' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=' => 'single_space',
                '=>' => 'single_space',
            ]
        ],
        'no_spaces_around_offset' => [
            'positions' => [
                'inside',
                'outside'
            ]
        ],
        'type_declaration_spaces' => [
            'elements' => [
                'constant',
                'function',
                'property',
            ]
        ],
        'types_spaces' => [
            'space' => 'none',
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'case',
                'declare',
                'default',
                'switch',
                'try',
                'yield',
                'yield_from'
            ]
        ],
        'no_extra_blank_lines' => [
            'tokens' => [
                'attribute',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
                'use',
            ],
        ],
    ])
    ->setRiskyAllowed(true)
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.tools/cache/php-cs-fixer.json')
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
