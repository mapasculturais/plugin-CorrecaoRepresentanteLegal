<?php
$app = MapasCulturais\App::i();
$em = $app->em;
$conn = $em->getConnection();

return [
    'cria agente padrão' => function () use ($app) {
        if (!$review_user = $app->repo("User")->findOneBy(["email" => "revisar-cadastro@mapas.com"])) {
            $app->disableAccessControl();
            $review_user = new \MapasCulturais\Entities\User;
            $review_user->authProvider = 'local';
            $review_user->authUid = 'revisar-cadastro@mapas.com';
            $review_user->email = 'revisar-cadastro@mapas.com';
            $review_user->save(true);
            $app->log->debug("Usuario fake criado {$review_user->id}");
            $app->enableAccessControl();
        }
    },
];
