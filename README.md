# Filipino Cookbook API

A secured REST API built with the Slim Framework (PHP) and MySQL, providing structured data on Filipino foods, their categories, origins, and ingredients. Protected endpoints require token-based authentication.

## API Description

- **Purpose**: Serve structured data about Filipino dishes — including categories, regional origins, and ingredients — over a REST API.
- **Type of information provided**: Filipino food records (name, category, origin, cooking instructions, ingredients), food categories, origins, and ingredient lists.
- **Intended users**: Developers building client applications (web/mobile) that need Filipino food data, or students consuming this API for a classroom driver/client app activity.
- **Main functions**: Retrieve all foods, retrieve a single food by ID, search foods by name, retrieve categories, retrieve ingredients, and add new food records.
- **Technologies used**: PHP, Slim Framework, MySQL (via PDO), Composer, Apache/XAMPP.

## Features

- Retrieve all Filipino foods, with category, origin, and ingredient details
- Retrieve a single food by ID
- Search foods by name
- Retrieve all food categories
- Retrieve all ingredients
- Add a new food record (with linked ingredients)
- Token-based authentication on all `/api` routes
- JSON responses throughout, with consistent error formatting

## Technologies Used

- PHP 8+
- Slim Framework 4 (`slim/slim`, `slim/psr7`)
- MySQL (PDO)
- Composer
- Apache (via XAMPP)
- Thunder Client (for testing)
- Git / GitHub

## Installation Instructions

1. Clone the repository:
   ```bash
   git clone https://github.com/arjaygarcia-star/filipino-cookbook-api-garcia.git
   cd filipino-cookbook-api-garcia
   ```
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Create a MySQL database named `filipino_cookbook_api` and import the provided SQL file (via phpMyAdmin or the MySQL CLI).
4. Copy the example config and fill in your local database credentials:
   ```bash
   cp config.example.php config.php
   ```
   Then edit `config.php` with your actual database username/password.
5. Place the project in your Apache web root (e.g. `C:\xampp\htdocs\`) or run PHP's built-in server:
   ```bash
   php -S localhost:8000 -t public
   ```
6. Start Apache and MySQL (via XAMPP Control Panel), then test the API (see below).

## Database Setup

- **Database name**: `filipino_cookbook_api`
- **Tables**: `categories`, `origins`, `foods`, `ingredients`, `food_ingredients`
- **Relationships**:
  ```
  categories -> foods <- origins
  foods -> food_ingredients <- ingredients
  ```
  Each food belongs to one category and one origin. Each food can have many ingredients (via the `food_ingredients` junction table).

## Base URL

```
http://localhost/filipino-cookbook-api-garcia/public/api
```
(or `http://localhost:8000/api` if using PHP's built-in server)

## Authentication Instructions

All routes under `/api` require a Bearer token in the request header:
```
Authorization: Bearer YOUR_ACCESS_TOKEN
```
If the token is missing or incorrect, the API responds with:
```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}
```
with HTTP status **401 Unauthorized**.

## Endpoint Documentation

### `GET /`
**Description**: Public welcome message. No token required.

**Example response**:
```json
{
  "message": "Welcome to the Secured Filipino Cookbook API",
  "note": "Use a valid Bearer token to access /api endpoints."
}
```

---

### `GET /api/foods`
**Description**: Returns all Filipino foods with category, origin, and ingredients.

**Required headers**:
```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Example response**:
```json
[
  {
    "food_id": 1,
    "food_name": "Adobo",
    "category_name": "Main Dish",
    "origin_name": "Philippines",
    "instructions": "Marinate the meat with soy sauce, vinegar, garlic, bay leaves, and peppercorn...",
    "ingredients": ["Bay leaves", "Chicken or pork", "Cooking oil", "Garlic", "Peppercorn", "Soy sauce", "Vinegar"]
  }
]
```

---

### `GET /api/foods/{id}`
**Description**: Returns a single food record by ID.

**Example error response** (ID not found):
```json
{
  "status": "error",
  "message": "Food not found"
}
```
Status: **404 Not Found**

---

### `GET /api/foods/search/{name}`
**Description**: Search foods by name (partial match).

**Example**: `GET /api/foods/search/adobo`

---

### `GET /api/categories`
**Description**: Returns all food categories.

---

### `GET /api/ingredients`
**Description**: Returns all ingredients.

---

### `POST /api/foods`
**Description**: Adds a new food record and links it to ingredients.

**Required body**:
```json
{
  "food_name": "Dinengdeng",
  "category_id": 3,
  "origin_id": 4,
  "instructions": "Boil vegetables with bagoong-based broth and add grilled fish before serving.",
  "ingredient_ids": [10, 15, 22]
}
```

**Example response**:
```json
{
  "status": "success",
  "message": "Food added successfully."
}
```
Status: **201 Created**

## Optional API Enhancements

In addition to the core required endpoints, the following endpoints were added to extend the API's functionality:

### `GET /api/foods/{id}/ingredients`
**Description**: Returns just the ingredients for a specific food, along with the food's ID and name.

**Required headers**:
```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Example response**:
```json
{
  "food_id": 1,
  "food_name": "Adobo",
  "ingredients": [
    { "ingredient_id": 3, "ingredient_name": "Bay leaves" },
    { "ingredient_id": 7, "ingredient_name": "Garlic" },
    { "ingredient_id": 12, "ingredient_name": "Soy sauce" }
  ]
}
```

**Error response** (food ID not found):
```json
{
  "status": "error",
  "message": "Food not found"
}
```
Status: **404 Not Found**

---

### `GET /api/ingredients/{id}/foods`
**Description**: Returns all foods that use a given ingredient, along with each food's category and origin.

**Example response**:
```json
{
  "ingredient_id": 7,
  "ingredient_name": "Garlic",
  "foods": [
    { "food_id": 1, "food_name": "Adobo", "category_name": "Main Dish", "origin_name": "Philippines" },
    { "food_id": 5, "food_name": "Sinigang", "category_name": "Soup", "origin_name": "Philippines" }
  ]
}
```

**Error response** (ingredient ID not found):
```json
{
  "status": "error",
  "message": "Ingredient not found"
}
```
Status: **404 Not Found**

---

### `GET /api/categories/foods-count`
**Description**: Returns every category along with a count of how many foods belong to it. Categories with no foods yet still appear, with a count of 0.

**Example response**:
```json
[
  { "category_id": 1, "category_name": "Dessert", "food_count": 4 },
  { "category_id": 2, "category_name": "Main Dish", "food_count": 9 },
  { "category_id": 3, "category_name": "Soup", "food_count": 2 }
]
```

**Why these were added**: These endpoints support more targeted lookups than the base `/api/foods` endpoint alone — letting a client app fetch just a food's ingredient list, find dishes by ingredient (useful for "what can I cook with X" style features), and show category statistics without having to fetch and manually count all foods client-side.


## HTTP Status Codes

| Status Code | Meaning |
|---|---|
| 200 | Request completed successfully |
| 201 | Resource created successfully |
| 400 | Invalid request or parameter |
| 401 | Missing or invalid authentication |
| 403 | Access is not allowed |
| 404 | Requested resource was not found |
| 429 | Too many requests |
| 500 | Internal server error |

## Testing Evidence

_(Add screenshots here of: a successful `/api/foods` request, a request without a token showing the 401 response, a `/api/foods/{id}` request for a non-existent ID showing 404, and the successful `POST /api/foods` request showing 201.)_

## Developer Information

- **Name**: Arjay Garcia
- **Course & Section**: _(BSIT-4B)_
- **GitHub username**: arjaygarcia-star
- **Repository**: https://github.com/arjaygarcia-star/filipino-cookbook-api-garcia
- **Date completed**: _(08/25/26)_
