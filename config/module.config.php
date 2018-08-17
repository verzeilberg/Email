<?php

namespace Email;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Factory\InvokableFactory;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;

return [
    'controllers' => [
        'factories' => [
            Controller\EmailController::class => Factory\EmailControllerFactory::class,
        ],
        'aliases' => [
            'emailbeheer' => Controller\EmailController::class,
        ],
    ],
    'service_manager' => [
        'invokables' => [
            'Email\Service\contactServiceInterface' => 'Email\Service\contactService',
            'Email\Service\emailReaderServiceInterface' => 'Email\Service\emailReaderService'
        ],
    ],
    // The following section is new and should be added to your file
    'router' => [
        'routes' => [
            'email' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/email[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => 'emailbeheer',
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'email' => __DIR__ . '/../view',
        ],
    ],
    // The 'access_filter' key is used by the User module to restrict or permit
    // access to certain controller actions for unauthorized visitors.
    'access_filter' => [
        'controllers' => [
            'emailbeheer' => [
                // to anyone.
                ['actions' => '*', 'allow' => '+email.manage']
            ],
        ]
    ],
    'asset_manager' => [
        'resolver_configs' => [
            'paths' => [
                __DIR__ . '/../public',
            ],
        ],
    ],
];