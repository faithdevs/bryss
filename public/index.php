<?php

use Bryss\App;
use Bryss\Validator as InputValidator;
use Bryss\Utils as Util;

require __DIR__ . '/../vendor/autoload.php';

$app = App::create();

// Currently supported methods: GET, POST, PUT, DELETE

$app->get("/api/v1/todos", function ($req, $res, $args) {
    $data = file_get_contents(__DIR__ ."/../public/todos.json");
    $jsonIterator = new RecursiveIteratorIterator(
        new RecursiveArrayIterator(json_decode($data, true)),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $todos = [];
    foreach ($jsonIterator as $key => $val) {
        if (is_array($val)) {
            if (is_string($key)) {
                $todos[$key] = $val;
            }
        }
    }
    return $res->json($todos);
});

$app->post("/api/v1/todos/create", function ($req, $res, $args) {
    // $name = $req->params["name"];
    $body = $req->getBody();
    $title = $req->input("title");

    $validator = InputValidator::schema($body, array(
        "title"=>"required|min:2",
    ));

    if (count($validator)!=0) {
        return $req->json(array(
            "status"=>"error",
            "message"=>"Validation error",
            "errors"=>$validator
        ), 400);
    }

    $path = __DIR__ ."/../public/todos.json";

    $data = file_get_contents($path);

    $jsonIterator = new RecursiveIteratorIterator(
        new RecursiveArrayIterator(json_decode($data, true)),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $todos = [];
    foreach ($jsonIterator as $key => $val) {
        if (is_array($val)) {
            if (is_string($key)) {
                $todos[$key] = $val;
            }
        }
    }
    $todos['todo'][] = [
        "id" => count($todos['todo'])+1,
        "title" => $title,
        "completed" => false
    ];

    file_put_contents($path, json_encode($todos));

    return $res->json($todos);
});

$app->put("/api/v1/todos/:id", function ($req, $res) {
    $body = $req->getBody();
    $title = $req->input("title");
    $completed = $req->input("completed");

    $validator = InputValidator::schema($body, array(
        "title"=>"required|min:2",
    ));

    if (count($validator)!=0) {
        return $req->json(array(
            "status"=>"error",
            "message"=>"Validation error",
            "errors"=>$validator
        ), 400);
    }
    $path = __DIR__ ."/../public/todos.json";

    $data = file_get_contents($path);

    $jsonIterator = new RecursiveIteratorIterator(
        new RecursiveArrayIterator(json_decode($data, true)),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $todos = [];
    foreach ($jsonIterator as $key => $val) {
        if (is_array($val)) {
            if (is_string($key)) {
                $todos[$key] = $val;
            }
        }
    }
    $item = null;
    foreach ($todos['todo'] as &$todo) {
        if ($todo['id'] == $req->params["id"]) {
            $todo = [
                "id" => $todo['id'],
                "title" => $title,
                "completed" => $completed
            ];
            $item = $todo;
        }
    }
    if (!$item) {
        return $res->json(array(
            "message"=>"Todo with this ID not found"
        ), 404);
    }
    file_put_contents($path, json_encode($todos));

    return $res->json($item);
});

$app->delete("/api/v1/todos/:id", function ($req, $res) {
    $path = __DIR__ ."/../public/todos.json";

    function unsetValue(array $array, $value, $strict = true)
    {
        if (($key = array_search($value, $array, $strict)) !== false) {
            unset($array[$key]);
        }
        return $array;
    }

    $data = file_get_contents($path);

    $jsonIterator = new RecursiveIteratorIterator(
        new RecursiveArrayIterator(json_decode($data, true)),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $todos = [];
    foreach ($jsonIterator as $key => $val) {
        if (is_array($val)) {
            if (is_string($key)) {
                $todos[$key] = $val;
            }
        }
    }
    $item = null;
    foreach ($todos['todo'] as $todo => $key) {
        if ($key['id'] == $req->params["id"]) {
            $item = $key;
            unset($todos['todo'][$todo]);
        }
    }
    if (!$item) {
        return $res->json(array(
            "message"=>"Todo with this ID not found"
        ), 404);
    }
    file_put_contents($path, json_encode($todos));

    return $res->json([$item, "message" => "Deleted successfully"]);
});

// Post endpoint with validation

$app->post('/api/v1/register', function ($req, $res) {
    $body = $req->getBody();
    $email = $req->input("email");
    $password = $req->input("password");
    $name = $req->input("name");

    $validator = InputValidator::schema($body, array(
        "email"=>"required|email",
        "password"=>"required|min:8",
        "name"=>"required|min:2",
    ));

    if (count($validator)!=0) {
        return $req->json(array(
            "status"=>"error",
            "message"=>"Validation error",
            "errors"=>$validator
        ), 400);
    }

    $path = __DIR__ ."/../public/users.json";

    $data = file_get_contents($path);

    $jsonIterator = new RecursiveIteratorIterator(
        new RecursiveArrayIterator(json_decode($data, true)),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $users = [];
    foreach ($jsonIterator as $key => $val) {
        if (is_array($val)) {
            if (is_string($key)) {
                $users[$key] = $val;
            }
        }
    }

    $item = null;
    foreach ($users['users'] as &$user) {
        if ($user['email'] == $email) {
            $item = $user;
        }
    }

    if ($item) {
        return $res->json(array(
            "message"=>"Email address already exists"
        ), 404);
    }

    $users['users'][] = [
        "id" => count($users['users'])+1,
        "email" => $email,
        "password" => $password,
        "name" => $name
    ];

    file_put_contents($path, json_encode($users));

    return $res->json(array(
        "status"=>"success",
        "message"=>"Registration successful",
        "data"=>$body
    ), 200);
});

$app->post('/api/v1/login', function ($req, $res) {
    $body = $req->getBody();
    $email = $req->input("email");
    $password = $req->input("password");

    $validator = InputValidator::schema($body, array(
        "email"=>"required|email",
        "password"=>"required|min:8",
    ));

    if (count($validator)!=0) {
        return $req->json(array(
            "status"=>"error",
            "message"=>"Validation error",
            "errors"=>$validator
        ), 400);
    }

    $path = __DIR__ ."/../public/users.json";

    $data = file_get_contents($path);

    $jsonIterator = new RecursiveIteratorIterator(
        new RecursiveArrayIterator(json_decode($data, true)),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $users = [];
    foreach ($jsonIterator as $key => $val) {
        if (is_array($val)) {
            if (is_string($key)) {
                $users[$key] = $val;
            }
        }
    }
    $item = null;
    foreach ($users['users'] as &$user) {
        if ($user['email'] == $email && $user['password'] == $password) {
            $item = $user;
        }
    }
    if (!$item) {
        return $res->json(array(
            "message"=>"Incorrect email address and password"
        ), 404);
    }

    return $res->json(array(
        "status"=>"success",
        "message"=>"Login successful",
        "data"=>Util::generateToken(64)
    ), 200);
});
