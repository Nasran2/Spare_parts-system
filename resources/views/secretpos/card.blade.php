<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings['shop_name'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap');
        body { font-family: 'Roboto', sans-serif; }
        .brand-font { font-family: 'Rajdhani', sans-serif; }
        .glass-card {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen bg-[url('https://images.unsplash.com/photo-1486262715619-72a607e3d511?q=80&w=2670&auto=format&fit=crop')] bg-cover bg-center flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gradient-to-br from-slate-900/90 via-slate-900/80 to-slate-900/90 mix-blend-multiply"></div>
    
    <div class="relative z-10 w-full max-w-md">
        <!-- Main Card -->
        <div class="glass-card rounded-2xl shadow-2xl overflow-hidden transform transition hover:scale-[1.01] duration-500">
            <!-- Header Pattern -->
            <div class="h-2 bg-gradient-to-r from-red-600 via-orange-500 to-red-600"></div>
            
            <div class="p-8 text-center">
                <!-- Logo Section -->
                <div class="relative mb-6 group inline-block">
                    <div class="absolute inset-0 bg-red-500 blur-xl opacity-20 group-hover:opacity-40 transition duration-500 rounded-full"></div>
                    @if($settings['shop_logo'])
                        <img src="{{ asset($settings['shop_logo']) }}" alt="Logo" 
                             class="relative h-28 w-28 object-contain mx-auto rounded-full bg-slate-800 border-4 border-slate-700 shadow-xl cursor-pointer hover:border-red-500 transition-all duration-300"
                             onclick="openModal()" title="Access System">
                    @else
                        <div class="relative h-28 w-28 mx-auto rounded-full bg-slate-800 border-4 border-slate-700 flex items-center justify-center cursor-pointer hover:border-red-500 transition-all duration-300 shadow-xl"
                             onclick="openModal()" title="Access System">
                            <i class="fas fa-car-battery text-4xl text-slate-400 group-hover:text-red-500 transition-colors"></i>
                        </div>
                    @endif
                    <div class="absolute -bottom-2 right-0 bg-red-600 text-white text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider shadow-lg">System Active</div>
                </div>

                <!-- Business Info -->
                <h1 class="text-3xl font-bold text-white mb-2 brand-font uppercase tracking-wide">{{ $settings['shop_name'] }}</h1>
                <p class="text-red-400 font-medium mb-6 uppercase tracking-widest text-xs">{{ $settings['shop_tagline'] ?? 'Premium Auto Parts & Service' }}</p>

                <div class="space-y-3 mb-8">
                    @if($settings['shop_address'])
                        <div class="flex items-center justify-center text-slate-300 text-sm bg-slate-800/50 py-2 px-4 rounded-lg border border-white/5">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                            {{ $settings['shop_address'] }}
                        </div>
                    @endif
                    
                    <div class="flex gap-2 justify-center">
                        @if($settings['shop_phone'])
                            <div class="flex items-center text-slate-300 text-sm bg-slate-800/50 py-2 px-4 rounded-lg border border-white/5 flex-1 justify-center">
                                <i class="fas fa-phone-alt text-red-500 mr-2"></i>
                                {{ $settings['shop_phone'] }}
                            </div>
                        @endif
                        @if($settings['shop_email'])
                            <div class="flex items-center justify-center text-slate-300 text-2xl bg-slate-800/50 py-2 px-3 rounded-lg border border-white/5 hover:text-white transition-colors" title="{{ $settings['shop_email'] }}">
                                <a href="mailto:{{ $settings['shop_email'] }}"><i class="fas fa-envelope"></i></a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer Hint -->
                <div class="text-xs text-slate-500 border-t border-white/10 pt-4 cursor-pointer hover:text-red-400 transition-colors" onclick="openModal()">
                    {{-- <i class="fas fa-lock mr-1"></i> Tap logo to access secured area --}}
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 transition-opacity duration-300 opacity-0">
        <div class="glass-card w-full max-w-[340px] rounded-2xl p-6 shadow-2xl transform scale-95 transition-transform duration-300" id="modalContent">
            <div class="flex items-center justify-between mb-6 border-b border-white/10 pb-4">
                <h2 class="text-lg font-bold text-white brand-font uppercase tracking-wider flex items-center">
                    <i class="fas fa-shield-alt text-red-500 mr-2"></i> Security Check
                </h2>
                <button class="text-slate-400 hover:text-white transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            @if($errors->has('code'))
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-sm p-3 rounded-lg mb-4 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i> {{ $errors->first('code') }}
                </div>
            @endif

            <form method="POST" action="{{ route('information.login') }}" class="space-y-6" novalidate onsubmit="return combinePin()">
                @csrf
                <input type="hidden" name="code" id="codeInput" value="">
                
                <div class="flex justify-between gap-2">
                    <input id="p1" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="w-14 h-16 text-center text-3xl font-bold bg-slate-800/80 border border-slate-600 text-white rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-500/20 outline-none transition-all brand-font" oninput="moveNext(this,'p2')" onkeydown="handleBack(event,'p1','p1')">
                    <input id="p2" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="w-14 h-16 text-center text-3xl font-bold bg-slate-800/80 border border-slate-600 text-white rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-500/20 outline-none transition-all brand-font" oninput="moveNext(this,'p3')" onkeydown="handleBack(event,'p2','p1')">
                    <input id="p3" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="w-14 h-16 text-center text-3xl font-bold bg-slate-800/80 border border-slate-600 text-white rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-500/20 outline-none transition-all brand-font" oninput="moveNext(this,'p4')" onkeydown="handleBack(event,'p3','p2')">
                    <input id="p4" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="w-14 h-16 text-center text-3xl font-bold bg-slate-800/80 border border-slate-600 text-white rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-500/20 outline-none transition-all brand-font" oninput="moveNext(this)" onkeydown="handleBack(event,'p4','p3')">
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 text-white uppercase tracking-wider font-bold rounded-xl py-3.5 shadow-lg shadow-red-900/30 transition-all active:scale-95 flex items-center justify-center brand-font text-lg">
                    Access Dashboard <i class="fas fa-arrow-right ml-2 text-sm opacity-70"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(){ 
            const m = document.getElementById('loginModal'); 
            const c = document.getElementById('modalContent');
            m.classList.remove('hidden'); 
            m.classList.add('flex'); 
            // Trigger reflow
            void m.offsetWidth;
            m.classList.remove('opacity-0');
            c.classList.remove('scale-95');
            setTimeout(()=>document.getElementById('p1').focus(), 100); 
        }
        
        function closeModal(){ 
            const m = document.getElementById('loginModal'); 
            const c = document.getElementById('modalContent');
            m.classList.add('opacity-0');
            c.classList.add('scale-95');
            setTimeout(() => {
                m.classList.add('hidden'); 
                m.classList.remove('flex'); 
            }, 300);
        }

        function moveNext(el, nextId){ 
            // Allow only numbers
            el.value = el.value.replace(/[^0-9]/g,''); 
            if(el.value && nextId){ 
                document.getElementById(nextId).focus(); 
            } else if (el.value && !nextId) {
                // Last digit entered
                el.blur();
                // Optional: auto-submit? Let's keep manual submit for safety
            }
        }

        function handleBack(e, curId, prevId){ 
            if(e.key === 'Backspace' && !document.getElementById(curId).value && prevId){ 
                document.getElementById(prevId).focus(); 
            } 
        }

        function combinePin(){
            const code = ['p1','p2','p3','p4'].map(id=> (document.getElementById(id).value||'').replace(/[^0-9]/g,'')).join('');
            if(code.length !== 4){ 
                // Shake animation for error
                const c = document.getElementById('modalContent');
                c.classList.add('animate-[shake_0.5s_ease-in-out]');
                setTimeout(()=>c.classList.remove('animate-[shake_0.5s_ease-in-out]'), 500);
                return false; 
            }
            document.getElementById('codeInput').value = code;
            return true;
        }

        // Add shake animation
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
