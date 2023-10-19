<?php

use MapasCulturais\App;
use MapasCulturais\Entities\User;
use MapasCulturais\i;
/* 
                   Nome do agente, cpf do agente, email do agente, short_description, 
                   */


/* Dados para criar uma conta obrigatorio: Email,cpf,senha(Aleatoria) */

/* Dados obrigatorio para uma criação de um agente: Nome, descrição, area de atuação */


return [
    'divide agente individuais de diversas contas' => function () {
        $app = App::i();

        DB_UPDATE::enqueue('User', 'id IN (SELECT user_id FROM agent WHERE type = 1 GROUP BY user_id HAVING count(*) > 1 LIMIT 10)', function (User $user) use ($app) {
            $conn = $app->em->getConnection();
            
            $agents = $conn->fetchAll("SELECT * FROM agent WHERE type = 1 AND user_id = {$user->id}");
            
            echo"----------------------------------------------------------------------------------------------------------------------------\n";
            echo"usuario: " .$user->id ."\n";
            foreach ($agents as $agent) {
                $id = $agent['id'];
                $agent['meta'] = $conn->fetchAll("SELECT jsonb_build_object(key,value) AS metadado FROM agent_meta WHERE object_id = {$id}");

                
                // $app->log->debug($agent);
                if($agent['meta']){

                }
                echo"Agente: " .$id ."\n";
                var_dump($agent);
                
               
            }



            // exit;
        });

        return false;
    }
];
