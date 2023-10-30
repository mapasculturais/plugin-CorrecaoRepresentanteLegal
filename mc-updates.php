<?php

use MapasCulturais\App;
use MapasCulturais\Entities\User;

return [
    'divide agente individuais de diversas contas' => function () {
        $app = App::i();
        DB_UPDATE::enqueue('User', 'id IN (SELECT user_id FROM agent WHERE type = 1 GROUP BY user_id HAVING count(*) > 1 LIMIT 30)', function (User $user) use ($app) {
            $conn = $app->em->getConnection();

            // Verifica o agente Principal do usuario.
            foreach ($user->agents as $agent) {
                if ($agent->id == $user->profile->id) {

                    if ($agent->emailPrivado != $user->email) {
                        foreach ($user->agents as $_agent) {

                            if ($_agent->emailPrivado == $user->email) {
                                $app->disableAccessControl();
                                $user->profile = $agent;
                                $user->save();
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

                        $pass = rand(111111, 999999);

                        $cpf = $_cpf;
                        $cpf = str_replace("-", "", $cpf); // remove "-"
                        $cpf = str_replace(".", "", $cpf); // remove "."

                        // generate the token hash
                        $source = rand(3333, 8888);
                        $cut = rand(10, 30);
                        $string = password_hash($source, PASSWORD_DEFAULT);
                        $token = substr($string, $cut, 20);

                        $app->disableAccessControl();
                        $_user = new \MapasCulturais\Entities\User;
                        $_user->authProvider = 'local';
                        $_user->authUid = filter_var($_email, FILTER_SANITIZE_EMAIL);
                        $_user->email = filter_var($_email, FILTER_SANITIZE_EMAIL);

                        $app->em->persist($_user);
                        $app->em->flush();

                        $agent->userId = $_user->id;
                        $agent->save(true);
                        $agent->refresh();
                        
                        $_user->profile = $agent;
                        $_user->save(true);
                        $app->enableAccessControl();

                        $baseUrl = $app->getBaseUrl();
                        $app->redirect($app->controller('auth')->createUrl(''), 401);
                        $url =$app->baseUrl . 'autenticacao/?t=' . $token;

                        $mustache = new \Mustache_Engine();
                        $site_name = $app->siteName;
                        $filename = $app->view->resolveFilename("views/emails", "new-account.html");
                        $template = file_get_contents($filename);
                        $content = $mustache->render(
                            $template,
                            array(
                                "url" => $url,
                                "siteName" => $site_name,
                                "user" => $_user->profile->name,
                                "urlToValidateAccount" =>  $baseUrl . 'auth/confirma-email?token=' . $token,
                                "baseUrl" => $baseUrl,
                                "urlSupportChat" => env('AUTH_SUPPORT_CHAT', ''),
                                "urlSupportEmail" => env('AUTH_SUPPORT_EMAIL', ''),
                                "urlSupportSite" => env('AUTH_SUPPORT_TEXT', ''),
                                "textSupportSite" => env('AUTH_SUPPORT_SITE', ''),
                                "urlImageToUseInEmails" => $app->view->asset('img/mail-image.png', false),
                            )
                        );

                        $app->createAndSendMailMessage([
                            'from' => $app->config['mailer.from'],
                            'to' => $_user->email,
                            'subject' => "Bem-vindo ao " . $site_name,
                            'body' => $content
                        ]);

                        $app->disableAccessControl();
                        $_user->{'localAuthenticationPassword'} = password_hash($pass, PASSWORD_DEFAULT);
                        $_user->{'tokenVerifyAccount'} = $token;
                        $_user->{'accountIsActive'} = '0';
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
