<?php

namespace Config;

use App\Validation\PasswordRules;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
        PasswordRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------

    /** @var array<string, array<string, list<string>|string>> */
    public array $registration = [
        'display_name' => [
            'label' => 'EduTest.displayName',
            'rules' => [
                'required',
                'max_length[120]',
            ],
        ],
        'email' => [
            'label' => 'EduTest.email',
            'rules' => [
                'required',
                'max_length[254]',
                'valid_email',
                'is_unique[users.email]',
            ],
        ],
        'phone' => [
            'label' => 'EduTest.phone',
            'rules' => [
                'permit_empty',
                'regex_match[/^\+?[0-9]{7,15}$/]',
            ],
        ],
        'password' => [
            'label' => 'EduTest.password',
            'rules' => [
                'required',
                'min_length[6]',
                'max_byte[72]',
            ],
            'errors' => [
                'max_byte' => 'EduTest.passwordTooLong',
            ],
        ],
        'password_confirm' => [
            'label' => 'EduTest.passwordConfirm',
            'rules' => [
                'required',
                'matches[password]',
            ],
        ],
    ];

    /** @var array<string, array<string, list<string>|string>> */
    public array $login = [
        'email' => [
            'label' => 'EduTest.email',
            'rules' => [
                'required',
                'max_length[254]',
                'valid_email',
            ],
        ],
        'password' => [
            'label' => 'EduTest.password',
            'rules' => [
                'required',
                'max_byte[72]',
            ],
        ],
    ];
}
