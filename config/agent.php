<?php

/*
|--------------------------------------------------------------------------
| AI Personal Office Employee
|--------------------------------------------------------------------------
|
| Konfigurasi runtime agent: penyedia LLM, batas eksekusi, kebijakan risiko,
| pembobotan pengambilan pengalaman, dan kanal percakapan (Telegram).
| Seluruh nilai sensitif tetap berada di .env atau tabel agent_integrations —
| tidak pernah ditulis ke dalam memori jangka panjang agent.
|
*/

return [

    // Saklar utama. Selama false, agent tetap dapat merencanakan & menjalankan
    // tool berisiko rendah, tetapi kanal keluar (Telegram) tidak diaktifkan.
    'enabled' => (bool) env('AGENT_ENABLED', true),

    'name' => env('AGENT_NAME', 'Asisten Kantor'),

    /*
    |----------------------------------------------------------------------
    | Penyedia LLM
    |----------------------------------------------------------------------
    | 'scripted' adalah perencana heuristik lokal yang deterministik: agent
    | tetap berjalan penuh (plan → execute → verify → reflect) tanpa API key
    | mana pun, sehingga test dan demo tidak bergantung pada jaringan.
    | 'anthropic' dipakai bila kunci tersedia (lihat agent_integrations).
    */
    'llm' => [
        'provider' => env('AGENT_LLM_PROVIDER', 'scripted'),
        'fallback' => 'scripted',
        'timeout'  => (int) env('AGENT_LLM_TIMEOUT', 60),

        'providers' => [
            'anthropic' => [
                'api_key'    => env('ANTHROPIC_API_KEY'),
                'base_url'   => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
                'version'    => '2023-06-01',
                'model'      => env('AGENT_LLM_MODEL', 'claude-opus-5'),
                'max_tokens' => (int) env('AGENT_LLM_MAX_TOKENS', 4096),
                // Penalaran adaptif: model mengatur sendiri kedalaman berpikir.
                'thinking'   => (bool) env('AGENT_LLM_THINKING', true),
                'effort'     => env('AGENT_LLM_EFFORT', 'medium'), // low|medium|high
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Batas eksekusi
    |----------------------------------------------------------------------
    | Runtime bekerja per-giliran (tick) dan menyimpan state setiap langkah,
    | sehingga task panjang tidak bergantung pada satu request HTTP.
    */
    'limits' => [
        'steps_per_tick'    => (int) env('AGENT_STEPS_PER_TICK', 12),
        'max_steps'         => (int) env('AGENT_MAX_STEPS', 40),
        'max_step_attempts' => (int) env('AGENT_MAX_STEP_ATTEMPTS', 3),
        'max_replans'       => (int) env('AGENT_MAX_REPLANS', 3),
        'step_timeout'      => (int) env('AGENT_STEP_TIMEOUT', 120),
        'approval_ttl_hours'=> (int) env('AGENT_APPROVAL_TTL_HOURS', 72),
    ],

    /*
    |----------------------------------------------------------------------
    | Kebijakan risiko & persetujuan manusia
    |----------------------------------------------------------------------
    | supervised : medium & high wajib disetujui manusia
    | balanced   : high wajib, medium wajib bila keyakinan rendah (bawaan)
    | autonomous : hanya high yang wajib disetujui
    */
    'policy' => [
        'autonomy'              => env('AGENT_AUTONOMY', 'balanced'),
        'confidence_threshold'  => (float) env('AGENT_CONFIDENCE_THRESHOLD', 0.55),
        'review_below'          => (float) env('AGENT_REVIEW_BELOW', 0.45),
        'blocked_tools'         => array_filter(explode(',', (string) env('AGENT_BLOCKED_TOOLS', ''))),
        // Tool berisiko tinggi selalu wajib approval, apa pun mode otonominya.
        'always_approve'        => ['email.send', 'calendar.create_event', 'whatsapp.send', 'file.delete'],
    ],

    /*
    |----------------------------------------------------------------------
    | Memori & pembelajaran pengalaman
    |----------------------------------------------------------------------
    */
    'memory' => [
        'store'      => env('AGENT_MEMORY_STORE', 'database'),
        'embedder'   => env('AGENT_EMBEDDER', 'hashing'), // deterministik, tanpa jaringan
        'dimensions' => 128,

        'retrieval' => [
            'experiences'      => 3,
            'lessons'          => 5,
            'procedures'       => 2,
            'semantic'         => 3,
            'min_score'        => 0.08,
            'recency_half_life'=> 45, // hari
        ],

        // Bobot skor pengalaman: similarity × success_rate × confidence × recency.
        'weights' => [
            'similarity' => 0.45,
            'success'    => 0.25,
            'confidence' => 0.15,
            'recency'    => 0.15,
        ],

        'confidence_step' => 0.08,  // naik/turun tiap kali pengalaman terbukti
        'obsolete_below'  => 0.15,
    ],

    /*
    |----------------------------------------------------------------------
    | Kanal percakapan
    |----------------------------------------------------------------------
    | Rahasia webhook dipakai sebagai bagian path URL sekaligus dicocokkan
    | dengan header X-Telegram-Bot-Api-Secret-Token.
    */
    'telegram' => [
        'enabled'        => (bool) env('AGENT_TELEGRAM_ENABLED', true),
        'api_url'        => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
        // Token boleh dari .env, boleh pula diberikan lewat layar onboarding
        // (tersimpan terenkripsi pada agent_integrations).
        'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'timeout'        => (int) env('TELEGRAM_TIMEOUT', 20),
        'link_ttl'       => (int) env('TELEGRAM_LINK_TTL', 30), // menit
    ],

    /*
    |----------------------------------------------------------------------
    | Katalog tool
    |----------------------------------------------------------------------
    | Menambah kemampuan agent cukup dengan menambahkan satu kelas di sini —
    | planner, executor, dan policy engine tidak perlu diubah.
    */
    'tools' => [
        App\Agent\Tools\Concrete\ClockTool::class,
        App\Agent\Tools\Concrete\NoteTool::class,
        App\Agent\Tools\Concrete\SpreadsheetReadTool::class,
        App\Agent\Tools\Concrete\DataAnalyzeTool::class,
        App\Agent\Tools\Concrete\DataCompareTool::class,
        App\Agent\Tools\Concrete\DocumentCreateTool::class,
        App\Agent\Tools\Concrete\FileWriteTool::class,
        App\Agent\Tools\Concrete\TaskSearchTool::class,
        App\Agent\Tools\Concrete\TaskReportTool::class,
        App\Agent\Tools\Concrete\EmailDraftTool::class,
        App\Agent\Tools\Concrete\EmailSendTool::class,
        App\Agent\Tools\Concrete\CalendarCreateEventTool::class,
        App\Agent\Tools\Concrete\WhatsAppSendTool::class,
    ],

    // Direktori kerja tool berbasis berkas (sandbox). Semua tool file dibatasi
    // di dalam direktori ini — tidak ada akses ke luar storage aplikasi.
    'workspace' => env('AGENT_WORKSPACE', 'agent'),
];
