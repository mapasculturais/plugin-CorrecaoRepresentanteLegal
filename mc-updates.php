<?php

use MapasCulturais\App;
use MapasCulturais\Entities\User;

return [
    'divide agente individuais de diversas contas' => function () {
        return false;
        $app = App::i();

        $review_user = new \MapasCulturais\Entities\User;
        $review_user->authProvider = 'local';
        $review_user->authUid = 'revisar-cadastro@mapas.com';
        $review_user->email = 'revisar-cadastro@mapas.com';

        $app->em->persist($review_user);
        $app->em->flush();

        DB_UPDATE::enqueue('User', 'id IN (SELECT user_id FROM agent WHERE type = 1 GROUP BY user_id HAVING count(*) > 1 )', function (User $user) use ($app, $review_user) {
            $conn = $app->em->getConnection();

            $old_agent_default = null;
            foreach ($user->agents as $agent) {
                if ($user->profile->id == $agent->id) {
                    $old_agent_default = $agent;
                    break;
                }
            }

            $old_update_agents = [];
            foreach ($user->agents as $agent) {
                if ($user->profile->id != $agent->id && $agent->type->id == 1) {
                    $old_update_agents[] = $agent;
                }
            }

            $email_owner_list = [];
            foreach ($old_update_agents as $agent) {
                $email_privado = $agent->emailPrivado;
                $nome = $agent->name;
                $cpf =  preg_replace('/[^0-9]/i', '', $agent->cpf);

                if (!$email_privado && !$cpf) {

                    $id = $conn->fetchScalar("SELECT nextval('agent_meta_id_seq'::regclass)");
                    $conn->insert('agent_meta', ['id' => $id, 'object_id' => $agent->id, 'key' => 'mc-revisar-cadastro', 'value' => 'revisar-cadastro-sem-email-e-sem-cpf']);

                    $id = $conn->fetchScalar("SELECT nextval('agent_meta_id_seq'::regclass)");
                    $conn->insert('agent_meta', ['id' => $id, 'object_id' => $agent->id, 'key' => 'mc-usuario-cadastro-origem', 'value' => $user->profile->id]);

                    continue;
                }

                $source = rand(3333, 8888);
                $cut = rand(10, 30);
                $string = password_hash($source, PASSWORD_DEFAULT);
                $token = substr($string, $cut, 20);

                if ($cpf && !$email_privado) {
                    $email_privado = $cpf . "@mapas";
                }

                $app->disableAccessControl();

                $new_user = new \MapasCulturais\Entities\User;
                $new_user->authProvider = 'local';
                $new_user->authUid = $email_privado;
                $new_user->email = $email_privado;

                $app->em->persist($new_user);
                $app->em->flush();

                $agent->userId = $new_user->id;
                $agent->save(true);
                $agent->refresh();

                $new_user->profile = $agent;
                $new_user->save(true);
                $email_owner_list[] = $agent->name;

                $baseUrl = $app->getBaseUrl();
                $url = $app->baseUrl . 'autenticacao/?t=' . $token;
                $site_name = $app->siteName;

                $dataValue = [
                    "siteName" => $site_name,
                    "user" => $new_user->profile->name,
                    "oldUser" => $user->profile->name,
                ];

                $message = $app->renderMailerTemplate('new_account', $dataValue);

                $app->createAndSendMailMessage([
                    'from' => $app->config['mailer.from'],
                    'to' => $new_user->email,
                    'subject' => $message['title'],
                    'body' => $message['body']
                ]);

                $pass = rand(111111, 999999);
                $new_user->{'localAuthenticationPassword'} = password_hash($pass, PASSWORD_DEFAULT);
                $new_user->{'tokenVerifyAccount'} = $token;
                $new_user->{'accountIsActive'} = '0';
                $new_user->save();

                $app->enableAccessControl();
            }
        });

        return false;
    }
];
