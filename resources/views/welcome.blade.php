<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CISMEDIC · API Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes subtle-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes fade-in {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slide-in {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .animate-in {
            animation: fade-in 1s ease-out forwards;
        }

        .float {
            animation: subtle-float 4s ease-in-out infinite;
        }

        .slide {
            animation: slide-in 0.8s ease-out forwards;
            opacity: 0;
        }

        .line {
            width: 1px;
            height: 80px;
            background: linear-gradient(to bottom, transparent, rgba(34, 197, 94, 0.5), transparent);
        }

        body {
            background: linear-gradient(135deg, #2C2C2C 0%, #1A1A1A 100%);
        }

        @media (max-width: 640px) {
            .line {
                height: 60px;
            }
        }
    </style>
</head>
<body class="text-white font-sans min-h-screen flex items-center justify-center overflow-hidden">
    <div class="flex flex-col items-center justify-center gap-8 md:gap-16 p-4 md:p-8 w-full max-w-5xl mx-auto">
        <!-- Logo Section -->
        <div class="relative animate-in w-full flex justify-center" style="animation-delay: 0.2s">
            <div class="line absolute left-1/2 -top-24 md:-top-24 transform -translate-x-1/2"></div>
            <div class="flex flex-col gap-6 md:gap-8 items-center">
                <div class="float" style="animation-delay: 0s">
                    <i class="fas fa-hospital-user text-5xl md:text-8xl text-green-400 opacity-80 transition-all duration-300 hover:opacity-100"></i>
                </div>
                <div class="float" style="animation-delay: 0.5s">
                    <i class="fas fa-database text-5xl md:text-8xl text-green-400 opacity-80 transition-all duration-300 hover:opacity-100"></i>
                </div>
            </div>
            <div class="line absolute left-1/2 -bottom-24 md:-bottom-24 transform -translate-x-1/2"></div>
        </div>

        <!-- Title -->
        <div class="text-center space-y-3 animate-in px-4 md:px-0" style="animation-delay: 0.4s">
            <h1 class="text-2xl md:text-6xl font-light tracking-[0.2em] md:tracking-[0.3em] uppercase break-words text-green-300">
                <span class="block md:inline">CISMEDIC</span>
                <span class="block text-sm md:text-2xl mt-2 md:mt-4 text-white/80">Backend Documentation</span>
            </h1>
            <div class="flex flex-col gap-2 md:gap-4 items-center mt-4 md:mt-8">
                <div class="text-[10px] md:text-xl tracking-[0.3em] md:tracking-[0.5em] text-green-200/60 uppercase slide" style="animation-delay: 0.6s">
                    API REST
                </div>
                <div class="text-[10px] md:text-xl tracking-[0.3em] md:tracking-[0.5em] text-green-200/60 uppercase slide" style="animation-delay: 0.8s">
                    Swagger 3.0
                </div>
            </div>
        </div>

        <!-- Button -->
        <a href="{{ url('/api/documentation') }}" 
           class="group border border-green-400/30 px-8 md:px-16 py-2.5 md:py-5 text-xs md:text-2xl tracking-[0.2em] uppercase 
                  hover:bg-green-400/10 transition-all duration-500 animate-in rounded-full md:rounded-none
                  hover:scale-105 active:scale-95 text-green-300"
           style="animation-delay: 1s">
            <span class="inline-flex items-center gap-3 md:gap-6">
                Documentación
                <i class="fas fa-arrow-right text-[10px] md:text-xl opacity-50 group-hover:opacity-100 transform transition-all duration-500 group-hover:translate-x-2"></i>
            </span>
        </a>
    </div>

    <!-- Version -->
    <div class="fixed bottom-4 md:bottom-8 text-[8px] md:text-lg tracking-[0.2em] md:tracking-[0.3em] text-green-200/40 uppercase animate-in" style="animation-delay: 1.2s">
        API v1.0
    </div>
</body>
</html>