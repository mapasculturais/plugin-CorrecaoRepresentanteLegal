<?php

namespace CorrecaoRepresentanteLegal;

use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{

    public function _init()
    {
        $app = App::i();

        $app->_config['mailer.templates']['new_account'] = [
            'title' => i::__("Bem vindo ao {$app->siteName}"),
            'template' => 'new-account.html'
        ];
    }

    public function  register()
    {
    }
}
