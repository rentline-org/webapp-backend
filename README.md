

## Quick Start

### Prerequisites
- PHP 8.2+
- Postgres
- Node.js 16+
- Composer

### Development Setup

1. **Clone and setup**
   ```bash
   git clone git@github.com:rentline-org/webapp-backend.git
   cd /webapp-backend
   cp .env.example .env
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   npx husky install
   ```

3. **Database setup**
   ```bash
   php artisan migrate --seed
   ```

4. **Start development**
   ```bash
   php artisan serve
   ```

### API Documentation

- **Scalar UI**: http://localhost:8000/docs
- **Swagger UI**: http://localhost:8000/docs/swagger
- **OpenAPI Spec**: http://localhost:8000/docs/openapi.yaml


## Commands

```bash
# Code generation
php artisan make:crud Product // All necessary skeleton files
php artisan make:dto ProductDTO
php artisan make:service Product/ProductService
php artisan make:repo Product/ProductRepository

# Code quality
php artisan pint
php artisan optimize:clear
php artisan ide-helper:generate
php artisan ide-helper:models -N
```

## License

This project is licensed under the `MIT License` - see the [LICENSE.md](LICENSE.md) file for details.
