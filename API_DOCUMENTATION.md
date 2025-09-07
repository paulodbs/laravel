# Gift Card E-commerce API Documentation

This is a complete Laravel-based API for an e-commerce system specializing in Gift Card sales with PagHiper payment integration.

## Authentication

The API uses Laravel Sanctum for token-based authentication. Most endpoints require authentication.

### Admin User Credentials
- Email: `admin@example.com`
- Password: `admin123@`

## Base URL
```
http://localhost:8000/api
```

## Authentication Endpoints

### Register
```http
POST /auth/register
Content-Type: application/json

{
    "name": "User Name",
    "email": "user@example.com",
    "password": "StrongPass123@",
    "password_confirmation": "StrongPass123@",
    "phone": "11999999999"
}
```

### Login
```http
POST /auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

### Get User Profile
```http
GET /auth/me
Authorization: Bearer {token}
```

### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

## Public Endpoints

### Get Categories
```http
GET /categories
```

### Get Category Details
```http
GET /categories/{id}
```

### Get Products
```http
GET /products?category_id=1&search=google&sort=popular
```

### Get Product Details
```http
GET /products/{id}
```

## User Endpoints (Authenticated)

### Create Order
```http
POST /orders
Authorization: Bearer {token}
Content-Type: application/json

{
    "items": [
        {
            "product_id": 1,
            "value": 25,
            "quantity": 2
        }
    ],
    "payment_method": "pix",
    "customer_name": "Customer Name",
    "customer_email": "customer@example.com"
}
```

### Get User Orders
```http
GET /orders/user/{user_id}
Authorization: Bearer {token}
```

### Create Payment
```http
POST /payment/create
Authorization: Bearer {token}
Content-Type: application/json

{
    "order_id": 1,
    "payment_method": "pix"
}
```

### Check Payment Status
```http
GET /payment/status/{order_id}
Authorization: Bearer {token}
```

### Create Support Ticket
```http
POST /tickets
Authorization: Bearer {token}
Content-Type: application/json

{
    "subject": "Need help with my order",
    "message": "I have a problem with my recent purchase",
    "priority": "medium"
}
```

## Admin Endpoints (Admin Role Required)

### Create Category
```http
POST /categories
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data

{
    "name": "New Category",
    "description": "Category description",
    "image": [file],
    "is_active": true,
    "sort_order": 1
}
```

### Create Product
```http
POST /products
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data

{
    "category_id": 1,
    "name": "Product Name",
    "description": "Product description",
    "image": [file],
    "price_options": [
        {"value": 10, "price": 12.00},
        {"value": 25, "price": 27.50}
    ],
    "is_active": true
}
```

### Upload Gift Codes
```http
POST /giftcodes/upload
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data

{
    "product_id": 1,
    "value": 25,
    "csv_file": [csv_file]
}
```

### Get All Orders
```http
GET /orders?status=pending&from_date=2024-01-01
Authorization: Bearer {admin_token}
```

### Update Order Status
```http
PUT /orders/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "status": "paid"
}
```

### Get PagHiper Settings
```http
GET /settings/paghiper
Authorization: Bearer {admin_token}
```

### Set PagHiper Settings
```http
POST /settings/paghiper
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "api_key": "your_paghiper_api_key",
    "token": "your_paghiper_token",
    "environment": "producao"
}
```

### Test PagHiper Credentials
```http
POST /settings/paghiper/test
Authorization: Bearer {admin_token}
```

## Webhook Endpoints

### PagHiper Payment Webhook
```http
POST /payment/paghiper/notification
Content-Type: application/json

{
    "transaction_id": "paghiper_transaction_id",
    "status": "paid"
}
```

## Response Formats

### Success Response
```json
{
    "message": "Operation successful",
    "data": {
        // Response data
    }
}
```

### Error Response
```json
{
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

### Pagination Response
```json
{
    "data": [...],
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "next_page_url": "...",
    "prev_page_url": null
}
```

## Sample Data

The seeder creates:
- Admin user: admin@example.com / admin123@
- 4 categories: Google Play, Netflix, Free Fire, Xbox
- 2 products with multiple price options
- 24 gift codes ready for testing

## Key Features

1. **JWT Authentication**: Token-based auth with refresh capability
2. **Role-Based Access**: Admin and user roles with different permissions
3. **Complete E-commerce Flow**: Categories, products, cart, orders
4. **Payment Integration**: Full PagHiper integration for PIX and Boleto
5. **Gift Code Management**: Bulk upload, status tracking, automatic assignment
6. **Support System**: Ticket creation and management
7. **Security**: Encrypted settings, proper validation, access control
8. **Admin Panel**: Complete backend API for admin dashboard

## Database Schema

The system uses 9 main tables:
- **users**: Authentication and user management
- **categories**: Product categorization
- **products**: Gift card products with price options
- **gift_codes**: Individual gift card codes
- **orders**: Customer orders
- **order_items**: Items within orders
- **payment_transactions**: PagHiper payment tracking
- **settings**: Encrypted configuration storage
- **tickets**: Customer support system

## Testing

Run the complete test suite:
```bash
php artisan test
```

Run specific feature tests:
```bash
php artisan test --filter=GiftCardEcommerceTest
```

## Installation

1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env`
4. Run `php artisan key:generate`
5. Run `php artisan migrate`
6. Run `php artisan db:seed --class=AdminUserSeeder`
7. Start the server: `php artisan serve`

The API will be available at `http://localhost:8000/api`