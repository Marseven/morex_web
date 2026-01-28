<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Morex API",
    description: "API pour l'application mobile Morex - Gestion des finances personnelles",
    contact: new OA\Contact(
        name: "Morex Support",
        email: "support@morex.app"
    )
)]
#[OA\Server(
    url: "/api",
    description: "API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Authentification via token Sanctum. Utilisez le token reçu lors du login."
)]
#[OA\Tag(name: "Auth", description: "Authentification et gestion des utilisateurs")]
#[OA\Tag(name: "Accounts", description: "Gestion des comptes bancaires")]
#[OA\Tag(name: "Categories", description: "Gestion des catégories de transactions")]
#[OA\Tag(name: "Transactions", description: "Gestion des transactions financières")]
#[OA\Tag(name: "Goals", description: "Gestion des objectifs d'épargne")]
#[OA\Tag(name: "Debts", description: "Gestion des dettes et créances")]
#[OA\Tag(name: "Stats", description: "Statistiques et tableaux de bord")]

// Schemas communs
#[OA\Schema(
    schema: "User",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "email", type: "string", format: "email"),
        new OA\Property(property: "phone", type: "string", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "Account",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "name", type: "string", example: "Compte principal"),
        new OA\Property(property: "type", type: "string", enum: ["cash", "bank", "mobile_money", "savings"]),
        new OA\Property(property: "initial_balance", type: "integer", example: 100000),
        new OA\Property(property: "balance", type: "integer", example: 150000),
        new OA\Property(property: "formatted_balance", type: "string", example: "150 000 FCFA"),
        new OA\Property(property: "color", type: "string", example: "#3B82F6"),
        new OA\Property(property: "icon", type: "string", example: "wallet"),
        new OA\Property(property: "is_default", type: "boolean"),
        new OA\Property(property: "order_index", type: "integer"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "Category",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "name", type: "string", example: "Alimentation"),
        new OA\Property(property: "type", type: "string", enum: ["expense", "income"]),
        new OA\Property(property: "color", type: "string", example: "#10B981"),
        new OA\Property(property: "icon", type: "string", example: "shopping-cart"),
        new OA\Property(property: "budget_limit", type: "integer", nullable: true, example: 50000),
        new OA\Property(property: "order_index", type: "integer"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "Transaction",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "amount", type: "integer", example: 25000),
        new OA\Property(property: "formatted_amount", type: "string", example: "25 000 FCFA"),
        new OA\Property(property: "type", type: "string", enum: ["expense", "income", "transfer"]),
        new OA\Property(property: "date", type: "string", format: "date", example: "2026-01-27"),
        new OA\Property(property: "beneficiary", type: "string", nullable: true),
        new OA\Property(property: "description", type: "string", nullable: true),
        new OA\Property(property: "category", ref: "#/components/schemas/Category", nullable: true),
        new OA\Property(property: "account", ref: "#/components/schemas/Account"),
        new OA\Property(property: "transfer_to_account", ref: "#/components/schemas/Account", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "Goal",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "name", type: "string", example: "Fonds d'urgence"),
        new OA\Property(property: "type", type: "string", enum: ["savings", "debt", "investment", "custom"]),
        new OA\Property(property: "target_amount", type: "integer", example: 2610000),
        new OA\Property(property: "current_amount", type: "integer", example: 500000),
        new OA\Property(property: "progress_percentage", type: "number", format: "float", example: 19.2),
        new OA\Property(property: "formatted_target_amount", type: "string", example: "2 610 000 FCFA"),
        new OA\Property(property: "formatted_current_amount", type: "string", example: "500 000 FCFA"),
        new OA\Property(property: "target_date", type: "string", format: "date", nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["active", "completed", "cancelled"]),
        new OA\Property(property: "color", type: "string", example: "#8B5CF6"),
        new OA\Property(property: "icon", type: "string", example: "target"),
        new OA\Property(property: "account", ref: "#/components/schemas/Account", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "Debt",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "name", type: "string", example: "Prêt famille"),
        new OA\Property(property: "type", type: "string", enum: ["debt", "credit"], description: "debt = je dois, credit = on me doit"),
        new OA\Property(property: "initial_amount", type: "integer", example: 100000),
        new OA\Property(property: "current_amount", type: "integer", example: 75000),
        new OA\Property(property: "paid_amount", type: "integer", example: 25000),
        new OA\Property(property: "remaining_amount", type: "integer", example: 75000),
        new OA\Property(property: "progress_percentage", type: "number", format: "float", example: 25.0),
        new OA\Property(property: "formatted_initial_amount", type: "string", example: "100 000 FCFA"),
        new OA\Property(property: "formatted_current_amount", type: "string", example: "75 000 FCFA"),
        new OA\Property(property: "due_date", type: "string", format: "date", nullable: true),
        new OA\Property(property: "days_until_due", type: "integer", nullable: true),
        new OA\Property(property: "is_overdue", type: "boolean"),
        new OA\Property(property: "description", type: "string", nullable: true),
        new OA\Property(property: "contact_name", type: "string", nullable: true),
        new OA\Property(property: "contact_phone", type: "string", nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["active", "paid", "cancelled"]),
        new OA\Property(property: "color", type: "string", example: "#EF4444"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "ValidationError",
    type: "object",
    properties: [
        new OA\Property(property: "message", type: "string", example: "The given data was invalid."),
        new OA\Property(
            property: "errors",
            type: "object",
            additionalProperties: new OA\AdditionalProperties(
                type: "array",
                items: new OA\Items(type: "string")
            )
        ),
    ]
)]
#[OA\Schema(
    schema: "PaginationMeta",
    type: "object",
    properties: [
        new OA\Property(property: "current_page", type: "integer"),
        new OA\Property(property: "from", type: "integer"),
        new OA\Property(property: "last_page", type: "integer"),
        new OA\Property(property: "per_page", type: "integer"),
        new OA\Property(property: "to", type: "integer"),
        new OA\Property(property: "total", type: "integer"),
    ]
)]
class SwaggerController
{
    // Ce controller sert uniquement à définir les annotations Swagger de base
}
