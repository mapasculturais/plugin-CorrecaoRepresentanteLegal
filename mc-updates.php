<?php

use MapasCulturais\App;
use MapasCulturais\Entities\User;
use MapasCulturais\Entities\AgentRelation;
use MapasCulturais\Entities\RequestEntityOwner;

return [
    'divide agentes individuais em diversas contas' => function () {
        $app = App::i();
        $review_user = $app->repo("User")->findOneBy(["email" => "revisar-cadastro@mapas.com"]);

        DB_UPDATE::enqueue('User', 'id IN (SELECT user_id FROM agent WHERE type = 1 AND status > 0 GROUP BY user_id HAVING count(*) > 1)', function (User $user) use ($app, $review_user) {
            $conn = $app->em->getConnection();
            $new_user =  null;

            $setHistoryModify = function ($agent) use ($conn, $user) {
                $id = $conn->fetchScalar("SELECT nextval('agent_meta_id_seq'::regclass)");
                $conn->insert('agent_meta', ['id' => $id, 'object_id' => $agent->id, 'key' => 'mc-usuario-cadastro-origem', 'value' => $user->profile->id]);
            };

            $setUserAgent = function ($agent, $user) use ($setHistoryModify) {
                $agent->userId = $user->id;
                $agent->save(true);
                $agent->refresh();
                $setHistoryModify($agent);

                $agent->deletePermissionsCache();
                $agent->enqueueToPCacheRecreation();

                return $agent;
            };

            $createUser = function ($app, $agent, $email_privado, $token) use ($setUserAgent, $conn, $setHistoryModify) {
                $new_user = new \MapasCulturais\Entities\User;
                $new_user->authProvider = 'local';
                $new_user->authUid = $email_privado;
                $new_user->email = $email_privado;
                $app->em->persist($new_user);
                $app->em->flush();

                $_agent = $setUserAgent($agent, $new_user);

                $new_user->profile = $_agent;
                $new_user->save(true);


                $pass = rand(111111, 999999);

                $id = $conn->fetchScalar("SELECT nextval('user_meta_id_seq'::regclass)");
                $conn->insert('user_meta', ['id' => $id, 'object_id' => $new_user->id, 'key' => 'localAuthenticationPassword', 'value' => password_hash($pass, PASSWORD_DEFAULT)]);

                $id = $conn->fetchScalar("SELECT nextval('user_meta_id_seq'::regclass)");
                $conn->insert('user_meta', ['id' => $id, 'object_id' => $new_user->id, 'key' => 'tokenVerifyAccount', 'value' => $token]);

                $id = $conn->fetchScalar("SELECT nextval('user_meta_id_seq'::regclass)");
                $conn->insert('user_meta', ['id' => $id, 'object_id' => $new_user->id, 'key' => 'accountIsActive', 'value' => '0']);

                $new_user->refresh();

                $setHistoryModify($agent);

                $new_user->profile->enqueueToPCacheRecreation();
                $new_user->enqueueToPCacheRecreation();

                return $new_user;
            };

            $sendEmailNewAccount = function ($new_user, $user, $urlRecovery) use ($app) {
                $dataValue = [
                    "siteName" => $app->siteName,
                    "user" => $new_user->profile->name,
                    "oldUser" => $user->profile->name,
                    "urlRecovery" => $urlRecovery
                ];

                $message = $app->renderMailerTemplate('new_account', $dataValue);

                $app->createAndSendMailMessage([
                    'from' => $app->config['mailer.from'],
                    'to' => $new_user->email,
                    'subject' => $message['title'],
                    'body' => $message['body']
                ]);
            };

            $sendEmailOldAccount = function ($user, $email_owner_lis) use ($app) {
                $dataValue = [
                    "siteName" => $app->siteName,
                    "user" => $user->profile->name,
                    "oldUsers" => $email_owner_lis,
                ];

                $message = $app->renderMailerTemplate('old_account', $dataValue);

                $app->createAndSendMailMessage([
                    'from' => $app->config['mailer.from'],
                    'to' => $user->email,
                    'subject' => $message['title'],
                    'body' => $message['body']
                ]);

                $user->profile->enqueueToPCacheRecreation();
                $user->enqueueToPCacheRecreation();
            };

            $requestEntityOwner = function ($_old, $_new) {
                $relation_class = $_new->getAgentRelationEntityClassName();
                $relation = new $relation_class;
                $relation->agent = $_old;
                $relation->owner = $_new;
                $relation->group = "group-admin";
                $relation->status = AgentRelation::STATUS_PENDING;
                $relation->hasControl = true;
                $relation->save(true);

                $request = new RequestEntityOwner($_old->user);
                $request->setAgentRelation($relation);

                $request->origin = $_old;
                $request->destination = $_new;
                $request->EntityOwner = $relation;
                $request->save(true);
            };

            /** @var Agent $old_agent_default*/
            $old_agent_default = null;
            foreach ($user->agents as $agent) {
                if ($user->profile->id == $agent->id) {

                    if($agent->type->id == 2){
                        $agent->setType(1);
                        $agent->status = 0;
                        $agent->cnpj = null;
                        $agent->save(true);
                        $agent->_newModifiedRevision();
                    }
                    $old_agent_default = $agent;
                    break;
                }
            }

            $old_update_agents = [];
            foreach ($user->agents as $agent) {
                if ($old_agent_default->id != $agent->id && $agent->type->id == 1) {
                    $old_update_agents[] = $agent;
                }
            }

            $app->disableAccessControl();
            $email_owner_list = [];
            foreach ($old_update_agents as $agent) {
                $email_owner_list[] = "ID_ANTIGO: $agent->id Nome: {$agent->name} E-mail {$agent->email}";

                $email_privado = $agent->emailPrivado ?: null;
                $cpf = $agent->cpf ? preg_replace('/[^0-9]/i', '', $agent->cpf) : null;

                if (!$email_privado && !$cpf) {
                    $id =  null;
                    $id = $conn->fetchScalar("SELECT nextval('agent_meta_id_seq'::regclass)");
                    $conn->insert('agent_meta', ['id' => $id, 'object_id' => $agent->id, 'key' => 'mc-revisar-cadastro', 'value' => 'revisar-cadastro-sem-email-e-sem-cpf']);

                    $setUserAgent($agent, $review_user);
                    continue;
                }

                $source = rand(3333, 8888);
                $cut = rand(10, 30);
                $string = password_hash($source, PASSWORD_DEFAULT);
                $token = substr($string, $cut, 20);

                if ($cpf && !$email_privado) {
                    $email_privado = $cpf . "@mapas";
                }

                $new_user = $createUser($app, $agent, $email_privado, $token);

                $requestEntityOwner($user->profile, $new_user->profile);

                $url = $app->baseUrl . 'autenticacao/?t=' . $token;
                $sendEmailNewAccount($new_user, $user, $url);
            }

            $sendEmailOldAccount($user, $email_owner_list);

            $app->enableAccessControl();
        });
    }
];
