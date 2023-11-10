<?php

namespace CorrecaoRepresentanteLegal;

use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{
    function __construct($config = [])
    {
        $app = App::i();
        $email_messages = [
            'new_account' => [
                'title' => i::__("Bem vindo ao {$app->siteName}"),
                'template' => 'new-account.html'
            ],
            'old_account' => [
                'title' => i::__("Alterações de agentes para atenter a LGPD no {$app->siteName}"),
                'template' => 'old-account.html'
            ],

        ];

        $app->_config['mailer.templates'] += $email_messages;

        parent::__construct($config);
    }

    public function _init()
    {
        $app = App::i();
    }

    public function  register()
    {
    }
}
