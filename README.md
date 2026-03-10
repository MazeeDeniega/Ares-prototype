# ARES — Automated Resume Evaluation System
---

## How to run locally
### Laravel
```bash
cd laravel-app
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### React
```bash
cd laravel-app
npm install
npm install @vitejs/plugin-react
npm run dev
```

### Python
```bash
cd nlp-service
pip install flask scikit-learn sentence-transformers spacy
python -m spacy download en_core_web_sm
python nlp_api.py
```
---
