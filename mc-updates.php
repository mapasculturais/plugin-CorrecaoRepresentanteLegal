<?php

use MapasCulturais\App;
use MapasCulturais\Entities\User;

return [
    'divide agente individuais de diversas contas' => function () {
        $app = App::i();
        DB_UPDATE::enqueue('User', 'id IN (SELECT user_id FROM agent WHERE type = 1 GROUP BY user_id HAVING count(*) > 1 LIMIT 10)', function (User $user) use ($app) {
            $conn = $app->em->getConnection();

            // Verifica o agente Principal do usuario.
            foreach ($user->agents as $agent) {
                if ($agent->id == $user->profile->id) {

                    if ($agent->emailPrivado != $user->email) {
                        foreach ($user->agents as $_agent) {

                            if ($_agent->emailPrivado == $user->email) {
                                $app->disableAccessControl();
                                $_user->profile = $agent;
                                $_user->save();
                                $app->enableAccessControl();
                                $_agent->user = $user;
                                $_agent->save();
                                break;
                            }
                        }
                    }
                    break;
                }
            }

            foreach ($user->agents as $agent) {
                if ($agent->id != $user->profile->id) {
                    $_email = $agent->emailPrivado;
                    $_name = $agent->name;
                    $_cpf =  $agent->cpf;

                    if ($_email || $_cpf) {

                        if ($_email) {
                            if ($_email == $user->email) {
                                // Adicionar flag revisar-cadastro
                                $id = $conn->fetchScalar("SELECT nextval('agent_meta_id_seq'::regclass)");
                                $conn->insert('agent_meta', ['id' => $id, 'object_id' => $agent->id, 'key' => 'revisar-cadastro', 'value' => 'revisar-cadastro']);
                                continue;
                            }
                        } else if ($_cpf) {
                            $_email = $_cpf . '@mapas';
                        } else {
                            // Adicionar flag revisar-cadastro
                            $id = $conn->fetchScalar("SELECT nextval('agent_meta_id_seq'::regclass)");
                            $conn->insert('agent_meta', ['id' => $id, 'object_id' => $agent->id, 'key' => 'revisar-cadastro', 'value' => 'revisar-cadastro']);
                            continue;
                        }

                        $app = App::i();
                        $config = $app->_config;

                        $pass = rand(111111, 999999);

                        $cpf = $_cpf;
                        $cpf = str_replace("-", "", $cpf); // remove "-"
                        $cpf = str_replace(".", "", $cpf); // remove "."

                        // generate the token hash
                        $source = rand(3333, 8888);
                        $cut = rand(10, 30);
                        $string = password_hash($source, PASSWORD_DEFAULT);
                        $token = substr($string, $cut, 20);

                        // Oauth pattern
                        $response = [
                            'auth' => [
                                'provider' => 'local',
                                'uid' => filter_var($_email, FILTER_SANITIZE_EMAIL),
                                'info' => [
                                    'email' => filter_var($_email, FILTER_SANITIZE_EMAIL),
                                    'name' => $_name,
                                    'cpf' => $cpf,
                                    'token' => $token
                                ],
                            ]
                        ];

                        //Removendo email em maiusculo
                        $response['auth']['uid'] = strtolower($response['auth']['uid']);
                        $response['auth']['info']['email'] = strtolower($response['auth']['info']['email']);

                        $app->applyHookBoundTo($this, 'auth.createUser:before', [$response]);
                        $_user = $this->_createUser($response);
                        $app->applyHookBoundTo($this, 'auth.createUser:after', [$_user, $response]);

                        $baseUrl = $app->getBaseUrl();

                        $mustache = new \Mustache_Engine();
                        $site_name = $app->siteName;
                        $content = $mustache->render(
                            file_get_contents(
                                __DIR__ .
                                    DIRECTORY_SEPARATOR . 'views' .
                                    DIRECTORY_SEPARATOR . 'auth' .
                                    DIRECTORY_SEPARATOR . 'email-to-validate-account.html'
                            ),
                            array(
                                "siteName" => $site_name,
                                "user" => $_user->profile->name,
                                "urlToValidateAccount" =>  $baseUrl . 'auth/confirma-email?token=' . $token,
                                "baseUrl" => $baseUrl,
                                "urlSupportChat" => $this->_config['urlSupportChat'],
                                "urlSupportEmail" => $this->_config['urlSupportEmail'],
                                "urlSupportSite" => $this->_config['urlSupportSite'],
                                "textSupportSite" => $this->_config['textSupportSite'],
                                "urlImageToUseInEmails" => $this->getImageImageURl(),
                            )
                        );

                        $app->createAndSendMailMessage([
                            'from' => $app->config['mailer.from'],
                            'to' => $_user->email,
                            'subject' => "Bem-vindo ao " . $site_name,
                            'body' => $content
                        ]);

                        $app->disableAccessControl();
                        $_user->{self::$passMetaName} = $app->auth->hashPassword($pass);
                        $_user->{self::$tokenVerifyAccountMetadata} = $token;
                        $_user->{self::$accountIsActiveMetadata} = '0';
                        $_user->profile = $agent;
                        $_user->save();
                        $app->enableAccessControl();
                    } else {
                        // Adicionar meta dado com fleg para revisar o Agente  (adicionar na tabela agent_meta com o key = 'revisar-cadastro')
                        $id = $conn->fetchScalar("SELECT nextval('agent_meta_id_seq'::regclass)");
                        $conn->insert('agent_meta', ['id' => $id, 'object_id' => $agent->id, 'key' => 'revisar-cadastro', 'value' => 'revisar-cadastro']);
                    }
                }
            }
        });

        return false;
    }
];
