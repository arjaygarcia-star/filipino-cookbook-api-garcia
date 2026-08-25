<?php

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/../vendor/autoload.php';

// ----------------------------------------------------------------
// APP SETUP
// ----------------------------------------------------------------
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// ----------------------------------------------------------------
// DATABASE CONNECTION (PDO)
// ----------------------------------------------------------------
function getDB(): PDO
{
    $host = 'localhost';
    $db   = 'filipino_cookbook_api';
    $user = 'root';
    $pass = '';

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ----------------------------------------------------------------
// JSON RESPONSE HELPER
// ----------------------------------------------------------------
function jsonResponse(Response $response, $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

// ----------------------------------------------------------------
// TOKEN MIDDLEWARE
// ----------------------------------------------------------------
$tokenCheck = function (Request $request, $handler) {
    $validToken = 'dmmmsu-cookbook-token-2026';
    $authHeader = $request->getHeaderLine('Authorization');

    if ($authHeader !== "Bearer $validToken") {
        $response = new \Slim\Psr7\Response();
        return jsonResponse($response, [
            'status'  => 'error',
            'message' => 'Unauthorized access. Valid API token is required.',
        ], 401);
    }

    return $handler->handle($request);
};

// ----------------------------------------------------------------
// PUBLIC ROUTE — WELCOME (no token required)
// ----------------------------------------------------------------
$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Welcome to the Secured Filipino Cookbook API',
        'note'    => 'Use a valid Bearer token to access /api endpoints.',
    ]);
});

// ----------------------------------------------------------------
// PROTECTED ROUTES — grouped under /api, all require the token
// ----------------------------------------------------------------
$app->group('/api', function ($group) {

    // 1. GET /api/foods — all foods with category, origin, ingredients
    $group->get('/foods', function (Request $request, Response $response) {
        $db = getDB();

        $stmt = $db->query("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
            FROM foods f
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            ORDER BY f.food_id
        ");
        $foods = $stmt->fetchAll();

        $ingredientStmt = $db->prepare("
            SELECT i.ingredient_name
            FROM food_ingredients fi
            JOIN ingredients i ON fi.ingredient_id = i.ingredient_id
            WHERE fi.food_id = ?
            ORDER BY i.ingredient_name
        ");

        foreach ($foods as &$food) {
            $ingredientStmt->execute([$food['food_id']]);
            $food['ingredients'] = array_column($ingredientStmt->fetchAll(), 'ingredient_name');
        }
        unset($food);

        return jsonResponse($response, $foods);
    });

    // 2. GET /api/foods/{id} — single food by ID (complete details)
    $group->get('/foods/{id}', function (Request $request, Response $response, $args) {
        $db = getDB();
        $id = $args['id'];

        $stmt = $db->prepare("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
            FROM foods f
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            WHERE f.food_id = ?
        ");
        $stmt->execute([$id]);
        $food = $stmt->fetch();

        if (!$food) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Food not found',
            ], 404);
        }

        $ingredientStmt = $db->prepare("
            SELECT i.ingredient_name
            FROM food_ingredients fi
            JOIN ingredients i ON fi.ingredient_id = i.ingredient_id
            WHERE fi.food_id = ?
            ORDER BY i.ingredient_name
        ");
        $ingredientStmt->execute([$id]);
        $food['ingredients'] = array_column($ingredientStmt->fetchAll(), 'ingredient_name');

        return jsonResponse($response, $food);
    });

    // 3. GET /api/foods/search/{name} — search food by name
    $group->get('/foods/search/{name}', function (Request $request, Response $response, $args) {
        $db = getDB();
        $name = $args['name'];

        $stmt = $db->prepare("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
            FROM foods f
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            WHERE f.food_name LIKE ?
            ORDER BY f.food_name
        ");
        $stmt->execute(['%' . $name . '%']);
        $foods = $stmt->fetchAll();

        $ingredientStmt = $db->prepare("
            SELECT i.ingredient_name
            FROM food_ingredients fi
            JOIN ingredients i ON fi.ingredient_id = i.ingredient_id
            WHERE fi.food_id = ?
            ORDER BY i.ingredient_name
        ");

        foreach ($foods as &$food) {
            $ingredientStmt->execute([$food['food_id']]);
            $food['ingredients'] = array_column($ingredientStmt->fetchAll(), 'ingredient_name');
        }
        unset($food);

        return jsonResponse($response, $foods);
    });

    // 4. GET /api/categories — all categories
    $group->get('/categories', function (Request $request, Response $response) {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM categories ORDER BY category_id");
        return jsonResponse($response, $stmt->fetchAll());
    });

    // 5. GET /api/ingredients — all ingredients
    $group->get('/ingredients', function (Request $request, Response $response) {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM ingredients ORDER BY ingredient_id");
        return jsonResponse($response, $stmt->fetchAll());
    });

    // 6. POST /api/foods — add a new food
    $group->post('/foods', function (Request $request, Response $response) {
        $db = getDB();
        $data = $request->getParsedBody();

        $foodName     = $data['food_name'] ?? null;
        $categoryId   = $data['category_id'] ?? null;
        $originId     = $data['origin_id'] ?? null;
        $instructions = $data['instructions'] ?? null;
        $ingredientIds = $data['ingredient_ids'] ?? [];

        if (!$foodName || !$categoryId || !$originId || !$instructions) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Missing required fields.',
            ], 400);
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO foods (food_name, category_id, origin_id, instructions)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$foodName, $categoryId, $originId, $instructions]);
            $foodId = $db->lastInsertId();

            $linkStmt = $db->prepare("
                INSERT INTO food_ingredients (food_id, ingredient_id)
                VALUES (?, ?)
            ");
            foreach ($ingredientIds as $ingredientId) {
                $linkStmt->execute([$foodId, $ingredientId]);
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Failed to add food: ' . $e->getMessage(),
            ], 500);
        }

        return jsonResponse($response, [
            'status'  => 'success',
            'message' => 'Food added successfully.',
        ], 201);
    });

    // 7. GET /api/foods/{id}/ingredients — ingredients of a specific food
    $group->get('/foods/{id}/ingredients', function (Request $request, Response $response, $args) {
        $db = getDB();
        $id = $args['id'];

        $checkStmt = $db->prepare("SELECT food_id, food_name FROM foods WHERE food_id = ?");
        $checkStmt->execute([$id]);
        $food = $checkStmt->fetch();

        if (!$food) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Food not found',
            ], 404);
        }

        $stmt = $db->prepare("
            SELECT i.ingredient_id, i.ingredient_name
            FROM food_ingredients fi
            JOIN ingredients i ON fi.ingredient_id = i.ingredient_id
            WHERE fi.food_id = ?
            ORDER BY i.ingredient_name
        ");
        $stmt->execute([$id]);
        $ingredients = $stmt->fetchAll();

        return jsonResponse($response, [
            'food_id'     => $food['food_id'],
            'food_name'   => $food['food_name'],
            'ingredients' => $ingredients,
        ]);
    });

    // 8. GET /api/ingredients/{id}/foods — foods containing a particular ingredient
    $group->get('/ingredients/{id}/foods', function (Request $request, Response $response, $args) {
        $db = getDB();
        $id = $args['id'];

        $checkStmt = $db->prepare("SELECT ingredient_id, ingredient_name FROM ingredients WHERE ingredient_id = ?");
        $checkStmt->execute([$id]);
        $ingredient = $checkStmt->fetch();

        if (!$ingredient) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Ingredient not found',
            ], 404);
        }

        $stmt = $db->prepare("
            SELECT f.food_id, f.food_name, c.category_name, o.origin_name
            FROM food_ingredients fi
            JOIN foods f ON fi.food_id = f.food_id
            JOIN categories c ON f.category_id = c.category_id
            JOIN origins o ON f.origin_id = o.origin_id
            WHERE fi.ingredient_id = ?
            ORDER BY f.food_name
        ");
        $stmt->execute([$id]);
        $foods = $stmt->fetchAll();

        return jsonResponse($response, [
            'ingredient_id'   => $ingredient['ingredient_id'],
            'ingredient_name' => $ingredient['ingredient_name'],
            'foods'           => $foods,
        ]);
    });

    // 9. GET /api/categories/foods-count — number of foods under each category
    $group->get('/categories/foods-count', function (Request $request, Response $response) {
        $db = getDB();

        $stmt = $db->query("
            SELECT c.category_id, c.category_name, COUNT(f.food_id) AS food_count
            FROM categories c
            LEFT JOIN foods f ON f.category_id = c.category_id
            GROUP BY c.category_id, c.category_name
            ORDER BY c.category_name
        ");
        $counts = $stmt->fetchAll();

        return jsonResponse($response, $counts);
    });

})->add($tokenCheck);

$app->run();
