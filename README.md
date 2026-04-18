# Frica Learn

Frica Learn is a comprehensive Learning Management System (LMS) built with Laravel, designed to facilitate online education with gamification features. It supports course creation, lesson delivery, quizzes, live classes, and student progress tracking, enhanced by AI-powered assistance and real-time notifications.

## Features

- **User Management**: Support for students, tutors, and parents with role-based access.
- **Course Management**: Create and organize courses into modules and lessons.
- **Interactive Learning**: Quizzes, questions, and live classes for engaging education.
- **Gamification**: Earn points, badges, and rewards; compete on leaderboards.
- **File Uploads**: Secure handling of attachments and media via Cloudinary.
- **AI Integration**: Powered by OpenAI for content assistance and insights.
- **Real-time Features**: Live notifications and messaging using Pusher.
- **Payment Integration**: Handle enrollment payments and redemptions.
- **Responsive Design**: Built with Tailwind CSS for a modern, mobile-friendly interface.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Vite, Tailwind CSS, Axios
- **Database**: MySQL/PostgreSQL (via Laravel migrations)
- **Authentication**: Laravel Sanctum
- **File Storage**: Cloudinary
- **Real-time**: Pusher
- **AI**: OpenAI API
- **Testing**: PHPUnit
- **Deployment**: Compatible with Laravel Sail or other hosting

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL or PostgreSQL database
- Git

### Steps

1. **Clone the repository**:
   ```bash
   git clone https://github.com/your-username/frica-learn.git
   cd frica-learn
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**:
   ```bash
   npm install
   ```

4. **Environment setup**:
   - Copy `.env.example` to `.env`:
     ```bash
     cp .env.example .env
     ```
   - Update the `.env` file with your database credentials, Cloudinary keys, Pusher keys, OpenAI API key, etc.

5. **Generate application key**:
   ```bash
   php artisan key:generate
   ```

6. **Run migrations**:
   ```bash
   php artisan migrate
   ```

7. **Seed the database (optional)**:
   ```bash
   php artisan db:seed
   ```

8. **Build assets**:
   ```bash
   npm run build
   ```

9. **Start the development server**:
   ```bash
   php artisan serve
   ```
   For frontend development:
   ```bash
   npm run dev
   ```

## Usage

- Access the application at `http://localhost:8000`.
- Register as a student, tutor, or parent.
- Tutors can create courses, add lessons, and schedule live classes.
- Students can enroll in courses, complete lessons, take quizzes, and redeem rewards.
- Use the dashboard to track progress and view leaderboards.

## Testing

Run the test suite with PHPUnit:
```bash
./vendor/bin/phpunit
```

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/your-feature`.
3. Commit your changes: `git commit -am 'Add new feature'`.
4. Push to the branch: `git push origin feature/your-feature`.
5. Submit a pull request.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

For questions or support, please open an issue on GitHub or contact the development team.

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
