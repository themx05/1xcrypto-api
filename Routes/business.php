<?php

use Core\MerchantProvider;
use Models\Document;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$businessRouter = new Router();

$businessRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        return $next();
    }
    return $res->json(Utils::buildErrors([],['requireAuth' => true]));
});

$businessRouter->post("/register", function(Request $req, Response $res){
    if($req->getOption('isAdmin')){
        return $res->json(Utils::buildErrors([],['requireAuth' => true]));
    }

    $user = $req->getOption('user');
    $merchantProvider = new MerchantProvider($req->getOption('storage'));

    $stored = $merchantProvider->getBusinessProfileByUser($user['id']);

    if($stored !== null){
        return $res->json(Utils::buildErrors(['default' => "Vous avez déja un profil business"]));
    }
    
    $upload_dir = $req->getOption('home')."/uploads";

    if(!is_dir($upload_dir)){
        mkdir($upload_dir,0775);
    }

    if(!isset($_POST['name'])){
        return $res->json(Utils::buildErrors(['name' => 'Le nom du profil business est requis']));
    }

    if($merchantProvider->getProfileByName($_POST['name']) !== null){
        return $res->json(Utils::buildErrors(['name' => 'Un profil business du meme nom existe deja']));
    }

    if(!isset($_POST['country'])){
        return $res->json(Utils::buildErrors(['country' => 'Le pays du partenaire doit etre mentionné']));
    }

    if(!isset($_POST['city'])){
        return $res->json(Utils::buildErrors(['city' => 'La ville du partenaire doit etre mentionné']));
    }

    if(!isset($_POST['phone'])){
        return $res->json(Utils::buildErrors(['phone' => 'Un contact téléphonique du partenaire doit etre mentionné']));
    }

    if($merchantProvider->getProfileByPhone($_POST['phone']) !==null){
        return $res->json(Utils::buildErrors(['phone' => 'Ce contact est deja utilisé par un partenaire']));
    }

    if(!isset($_POST['email'])){
        return $res->json(Utils::buildErrors(['email' => "L'adresse electronique du partenaire doit etre mentionné email"]));
    }

    if($merchantProvider->getProfileByEmail($_POST['email']) !== null){
        return $res->json(Utils::buildErrors(['email' => 'Un profil business utilise deja cette adresse electronique']));
    }

    if(!isset($_FILES['cni']) || empty($_FILES['cni']['tmp_name'])){
        return $res->json(Utils::buildErrors(['cni' => 'Une image de la C.N.I du partenaire doit etre fournie.']));
    }

    $business = [
        'name' => $_POST['name'],
        'country' => strtoupper($_POST['country']),
        'city' => Utils::protectString($_POST['city']),
        'phone' => Utils::protectString($_POST['phone']),
        'email' => Utils::protectString($_POST['email']),
        'documents' => []
    ];

    $allowed_extensions = array("jpeg","jpg","png", "pdf","doc");

    $cni_data = array();
    $rc_data = array();
    $ifu_data = array();

    if(isset($_FILES['cni']) && !empty($_FILES['cni']['tmp_name'])){
        $cni = $_FILES['cni'];
        $uploadName = Utils::generateHash();
        $ext = strtolower(end(explode(".",$cni['name'])));
        $uploadName = "$uploadName.$ext";
        if(in_array($ext,$allowed_extensions)){
            if(move_uploaded_file($cni['tmp_name'], $upload_dir."/".$uploadName)){
                array_push($business['documents'],[
                    'docType' => 'cni',
                    'fileType' => $ext,
                    'name' => $uploadName
                ]);
            }
            else{
                return $res->json(Utils::buildErrors(['message' => 'Echec d\'enregistrement de votre C.N.I']));
            }
        }else{
            return $res->json(Utils::buildErrors(['cni' => 'Le type de fichier soumis n\'est pas accepté']));
        }
    }

    if(isset($_FILES['rc']) && !empty($_FILES['rc']['tmp_name'])){
        $rc = $_FILES['rc'];
        $uploadName = Utils::generateHash();
        $ext = strtolower(end(explode(".",$rc['name'])));
        $uploadName = "$uploadName.$ext";
        if(in_array($ext,$allowed_extensions)){
            if(move_uploaded_file($rc['tmp_name'], $upload_dir."/".$uploadName)){
                array_push($business['documents'],[
                    'docType' => 'rc',
                    'fileType' => $ext,
                    'name' => $uploadName
                ]);
            }
            else{
                return $res->json(Utils::buildErrors(['rc' => 'Echec d\'enregistrement de votre R.C']));
            }
        }
        else{
            return $res->json(Utils::buildErrors(['rc' => 'Le type fichier n\'est pas accepté']));
        }
    }

    if(isset($_FILES['ifu']) && !empty($_FILES['ifu']['tmp_name'])){
        $ifu = $_FILES['ifu'];
        $uploadName = Utils::generateHash();
        $ext = strtolower(end(explode(".",$ifu['name'])));
        $uploadName = "$uploadName.$ext";
        if(in_array($ext,$allowed_extensions)){
            if(move_uploaded_file($ifu['tmp_name'], $upload_dir."/".$uploadName)){
                array_push($business['documents'],[
                    'docType' => 'ifu',
                    'fileType' => $ext,
                    'name' => $uploadName
                ]);
            }
            else{
                return $res->json(Utils::buildErrors(['ifu' => "Echec d'enregistrement de l'ifu"]));
            }
        }
        else{
            return $res->json(Utils::buildErrors(['ifu' => 'Le type de fichier n\'est ps accepté']));
        }
    }

    $businessId = $merchantProvider->createBusinessProfile($user['id'], json_decode(json_encode($business)));
    if(!empty($businessId)){
        return $res->json(Utils::buildSuccess($businessId));
    }
    else{
        return $res->json(Utils::buildErrors([],['message' => 'failed to create business profile']));
    }
});

$businessRouter->get("/", function(Request $req, Response $res){
    $merchantProvider = new MerchantProvider($req->getOption('storage'));
    
    if($req->getOption('isAdmin')){
        $profiles = $merchantProvider->getProfiles();
        return $res->json(Utils::buildSuccess($profiles));
    }
    else {
        $user = $req->getOption('user');
        $profile = $merchantProvider->getBusinessProfileByUser($user['id']);

        if(isset($profile)){
            return $res->json(Utils::buildSuccess($profile));
        }
    }

    return $res->json(Utils::buildErrors());
});

$singleBusiness = new Router();

$singleBusiness->get("/",function(Request $req, Response $res){
    $merchantProvider = new MerchantProvider($req->getOption('storage'));
    $business = $req->getParam('business');
    $profile = $merchantProvider->getProfileById($business);
    $user = $req->getOption('user');

    if($profile !== null){
        if($req->getOption('isAdmin') || $profile->userId === $user['id']){
            return $res->json(Utils::buildSuccess($profile));
        }
    }
    return $res->json(Utils::buildErrors());
});

$singleBusiness->get("/approve", function(Request $req, Response $res){
    $client = $req->getOption('storage');
    if($client instanceof PDO){
        $merchantProvider = new MerchantProvider($client);
        $business = $req->getParam('business');
        $profile = $merchantProvider->getProfileById($business);
        $user = $req->getOption('user');

        if($req->getOption('isAdmin')){
            if(isset($profile)){
                $client->beginTransaction();
                $done = $merchantProvider->approveProfile($profile->id);
                if($done){
                    $client->commit();
                    return $res->json(Utils::buildSuccess($done));
                }
                $client->rollBack();
            }
        }
    }
    return $res->json(Utils::buildErrors());
});

$singleBusiness->patch("/documents/:docName/verified/:enable", function(Request $req, Response $res){
    $client = $req->getOption('storage');
    $enable = intval($req->getParam('enable')) === 1;
    if($client instanceof PDO && $req->getOption('isAdmin')){
        $merchantProvider = new MerchantProvider($client);
        $business = $req->getParam('business');
        $docName = $req->getParam('docName');

        $profile = $merchantProvider->getProfileById($business);
        
        if($profile !== null){
            foreach($profile->documents as $key => $doc){
                $parsed = new Document($doc);
                if($parsed->name === $docName){
                    $parsed->verified = $enable;
                }
                $profile->documents[$key] = $doc;
            }
        }
        
        $client->beginTransaction();
        $done = $merchantProvider->updateProfile($profile);
        if($done){
            $client->commit();
            return $res->json(Utils::buildSuccess($done));
        }
        $client->rollBack();
    }
    return $res->json(['success' => false]);
});

$singleBusiness->delete("/", function(Request $req, Response $res){
    $merchantProvider = new MerchantProvider($req->getOption('storage'));
    $business = $req->getParam('business');
    $profile = $merchantProvider->getProfileById($business);
    $user = $req->getOption('user');

    if($req->getOption('isAdmin')){
        if(isset($profile)){
            $done = $merchantProvider->deleteProfile($profile->id);
            if($done){
                return $res->json(Utils::buildSuccess($done));
            }
        }
    }
    return $res->json(Utils::buildErrors());
});

$businessRouter->router("/:business", $singleBusiness);

global $application;
$application->router("/business", $businessRouter);

?>