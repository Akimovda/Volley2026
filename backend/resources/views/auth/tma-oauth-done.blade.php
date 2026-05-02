<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация прошла успешно</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,.1);
            max-width: 340px;
            width: 100%;
        }
        .icon { font-size: 3.5rem; margin-bottom: 1rem; }
        h2 { font-size: 1.25rem; color: #1a1a2e; margin-bottom: .5rem; }
        p { color: #666; font-size: .95rem; line-height: 1.5; margin-bottom: 1.5rem; }
        .btn {
            display: inline-block;
            padding: .75rem 1.75rem;
            background: #2196f3;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: .95rem;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h2>Авторизация прошла успешно!</h2>
        <p>Вернитесь в приложение, чтобы продолжить.</p>
        <button class="btn" onclick="window.close()">Закрыть</button>
    </div>
    <script>
        // Пробуем закрыть окно автоматически через 1.5 сек
        setTimeout(function() { window.close(); }, 1500);
    </script>
</body>
</html>
