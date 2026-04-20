/var/www/api_sangia/                    
│
├── .htaccess                           # Mengunci akses langsung, me-routing ke index.php
├── index.php                           # Entry point publik
└── switcher.php                        # Menangkap parameter, mengarahkan ke Gateway

/var/www/api_script/                    
│
├── gateway/                            # --- LAPIS PERTAHANAN (Di luar Core) ---
│   ├── AuthKeyValidator.php            # Mengecek API Key ke developers.sangia.org
│   └── RateLimiter.php                 # Membatasi kuota request per detik/menit
│
├── core/                               # --- INTI MESIN PEMROSES (Terisolasi) ---
│   │
│   ├── Modules/                        # (HMVC) Tempat tinggal berbagai API
│   │   │
│   │   ├── SDG/                        # Modul Klasifikasi SDG
│   │   │   ├── Controllers/            
│   │   │   │   ├── Analyze.php         
│   │   │   │   └── Worker.php          
│   │   │   ├── Config/
│   │   │   │   └── Dictionaries/       # Sdg1.php, Sdg2.php, dst.
│   │   │   └── Services/
│   │   │       ├── Evaluator/          # Logika penilaian per level (Basic, V4)
│   │   │       └── SdgClassifier.php   
│   │   │
│   │   └── Citation/                   # Modul API lain di masa depan
│   │
│   └── Shared/                         # Sumber daya yang bisa dipakai semua modul
│       ├── Database/                   
│       ├── ApiClients/                 # OrcidClient.php, CrossrefClient.php
│       └── Helpers/                    # TextHelper.php
│
├── library/                            # --- PENGGANTI VENDOR ---
│   └── ...                             # Berisi autoloader Composer & package eksternal
│
├── writable/                           # --- PENYIMPANAN DINAMIS ---
│   ├── cache/                          
│   ├── tasks/                          # Penyimpanan State untuk Anti-Timeout
│   └── logs/                           
│
└── composer.json                       # Konfigurasi proyek