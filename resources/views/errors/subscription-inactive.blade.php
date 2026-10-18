<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suscripción inactiva</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --rosa: #F43F78;
            --ciruela: #5B1E3D;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #fdf2f6 0%, #fbe4ec 100%);
            color: var(--ciruela);
        }
        .card {
            width: 100%;
            max-width: 30rem;
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 20px 45px -20px rgba(91, 30, 61, 0.35);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .icon {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: rgba(244, 63, 120, 0.12);
            color: var(--rosa);
            font-size: 2rem;
            font-weight: 700;
        }
        h1 {
            margin: 0 0 0.75rem;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--ciruela);
        }
        p {
            margin: 0 0 1.75rem;
            font-size: 1rem;
            line-height: 1.6;
            color: #6b4a58;
        }
        .btn {
            display: inline-block;
            border: 0;
            cursor: pointer;
            padding: 0.75rem 1.75rem;
            border-radius: 9999px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: var(--rosa);
            text-decoration: none;
            transition: background-color 0.15s ease;
        }
        .btn:hover { background: #d8366a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">!</div>
        <h1>Acceso no disponible</h1>
        <p>Tu suscripción venció o está inactiva. Contactá al administrador para reactivar tu cuenta.</p>

        @php($logoutRoute = \Illuminate\Support\Facades\Route::has('filament.boutique.auth.logout') ? route('filament.boutique.auth.logout') : null)
        @if ($logoutRoute)
            <form method="POST" action="{{ $logoutRoute }}">
                @csrf
                <button type="submit" class="btn">Cerrar sesión</button>
            </form>
        @else
            <a href="{{ url('/') }}" class="btn">Volver al inicio</a>
        @endif
    </div>
</body>
</html>
