<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Allo Delivery | الصفحة الرئيسية</title>
    <style>
        :root {
            --bg-1: #fff8ef;
            --bg-2: #f7efff;
            --surface: rgba(255, 255, 255, 0.82);
            --surface-strong: rgba(255, 255, 255, 0.96);
            --border: rgba(30, 41, 59, 0.10);
            --text: #1f2937;
            --muted: #6b7280;
            --brand: #f59e0b;
            --brand-strong: #d97706;
            --shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
            --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.08);
            --radius-xl: 28px;
            --radius-lg: 22px;
            --radius-md: 16px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            min-height: 100%;
        }

        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top right, rgba(245, 158, 11, 0.18), transparent 30%),
                radial-gradient(circle at top left, rgba(168, 85, 247, 0.12), transparent 26%),
                linear-gradient(180deg, var(--bg-1), #fffdf8 42%, var(--bg-2));
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .page {
            min-height: 100vh;
            min-height: 100svh;
            padding:
                max(16px, env(safe-area-inset-top))
                16px
                max(16px, env(safe-area-inset-bottom))
                16px;
            display: flex;
            justify-content: center;
        }

        .phone-shell {
            width: min(100%, 430px);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .hero {
            position: relative;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, var(--surface-strong), var(--surface));
            box-shadow: var(--shadow);
            border-radius: var(--radius-xl);
            padding: 18px;
            backdrop-filter: blur(16px);
            overflow: hidden;
        }

        .hero::before,
        .hero::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            background: rgba(245, 158, 11, 0.09);
            pointer-events: none;
        }

        .hero::before {
            width: 120px;
            height: 120px;
            top: -58px;
            left: -36px;
        }

        .hero::after {
            width: 160px;
            height: 160px;
            bottom: -110px;
            right: -50px;
        }

        .hero-top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            width: 54px;
            height: 54px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #fff8e1, #fff);
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.14);
            color: var(--brand);
            font-size: 20px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .brand-copy {
            min-width: 0;
        }

        .brand-copy h1 {
            margin: 0;
            font-size: clamp(20px, 4.8vw, 28px);
            line-height: 1.15;
            letter-spacing: -0.02em;
            color: #111827;
        }

        .brand-copy p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .hero-arrow {
            width: 48px;
            height: 48px;
            border: 0;
            border-radius: 50%;
            background: linear-gradient(180deg, #fff7e6, #fff);
            color: var(--brand);
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.14);
            font-size: 28px;
            line-height: 1;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .section {
            border: 1px solid var(--border);
            background: var(--surface-strong);
            box-shadow: var(--shadow-soft);
            border-radius: var(--radius-xl);
            padding: 18px;
        }

        .radio-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .radio-card {
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: linear-gradient(180deg, #fff, #fffaf2);
            border-radius: 20px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 64px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .radio-card span {
            font-size: 15px;
            font-weight: 600;
            color: #374151;
        }

        .radio-card input[type="radio"] {
            width: 20px;
            height: 20px;
            margin: 0;
            accent-color: var(--brand);
            flex-shrink: 0;
        }

        .form-card {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .field {
            border: 1px solid rgba(148, 163, 184, 0.20);
            background: linear-gradient(180deg, #fff, #fffdf9);
            border-radius: 20px;
            padding: 14px 16px;
        }

        .field-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .field-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #1f2937;
        }

        .field-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(245, 158, 11, 0.10);
            color: var(--brand-strong);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .slot-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .slot {
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .slot-label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .slot-value {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            letter-spacing: 0.01em;
        }

        .date-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: space-between;
            padding: 13px 14px;
            border-radius: 16px;
            background: linear-gradient(180deg, #fff8ee, #fff);
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .date-chip strong {
            font-size: 15px;
        }

        .date-chip span {
            color: #111827;
            font-weight: 700;
        }

        .action-wrap {
            display: grid;
            place-items: center;
            padding: 8px 0 4px;
        }

        .action-button {
            width: 96px;
            height: 96px;
            border: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 30%, #fff8ed, #ffe9cd 68%, #ffd9a7);
            color: var(--brand-strong);
            font-size: 44px;
            line-height: 1;
            box-shadow: 0 18px 40px rgba(245, 158, 11, 0.18);
        }

        .message-card {
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: var(--surface-strong);
            border-radius: 20px;
            padding: 14px;
            box-shadow: var(--shadow-soft);
        }

        .message-card label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
        }

        .message-card textarea {
            width: 100%;
            min-height: 92px;
            resize: vertical;
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 16px;
            padding: 14px 15px;
            font: inherit;
            color: #111827;
            background: linear-gradient(180deg, #fff, #fffdf9);
            outline: none;
        }

        .message-card textarea::placeholder {
            color: #9ca3af;
        }

        .bottom-actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
        }

        .secondary-note {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
        }

        .primary-button {
            border: 0;
            border-radius: 16px;
            min-height: 54px;
            padding: 0 22px;
            background: linear-gradient(180deg, #fbbf24, #f59e0b);
            color: white;
            font-size: 16px;
            font-weight: 800;
            box-shadow: 0 14px 24px rgba(245, 158, 11, 0.26);
            white-space: nowrap;
        }

        .footer-space {
            height: 6px;
        }

        @media (max-width: 380px) {
            .phone-shell {
                gap: 14px;
            }

            .hero,
            .section,
            .message-card {
                border-radius: 24px;
            }

            .radio-row,
            .slot-grid,
            .bottom-actions {
                grid-template-columns: 1fr;
            }

            .hero-top {
                align-items: flex-start;
            }

            .hero-arrow {
                width: 44px;
                height: 44px;
            }

            .action-button {
                width: 88px;
                height: 88px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="phone-shell">
            <section class="hero" aria-label="Allo Delivery">
                <div class="hero-top">
                    <div class="brand">
                        <div class="brand-mark">AD</div>
                        <div class="brand-copy">
                            <h1>صفحة الجدول الزمني</h1>
                            <p>تنسيق واضح وسهل الاستخدام لإدارة المواعيد والرسائل.</p>
                        </div>
                    </div>
                    <button class="hero-arrow" type="button" aria-label="الانتقال">
                        →
                    </button>
                </div>
            </section>

            <section class="section">
                <div class="radio-row" role="radiogroup" aria-label="نوع الاختيار">
                    <label class="radio-card">
                        <span>اختيار تاريخ</span>
                        <input type="radio" name="choice" checked>
                    </label>
                    <label class="radio-card">
                        <span>اختيار يوم/أيام</span>
                        <input type="radio" name="choice">
                    </label>
                </div>
            </section>

            <section class="section form-card" aria-label="إعداد الجدول">
                <div class="field">
                    <div class="field-head">
                        <h2 class="field-title">اختر التاريخ</h2>
                        <span class="field-badge">2026-06-29</span>
                    </div>

                    <div class="date-chip">
                        <span>الخيار الحالي</span>
                        <strong>2026-06-29</strong>
                    </div>
                </div>

                <div class="field">
                    <div class="field-head">
                        <h2 class="field-title">الوقت</h2>
                        <span class="field-badge">مريح وواضح</span>
                    </div>

                    <div class="slot-grid">
                        <div class="slot">
                            <span class="slot-label">الوقت من</span>
                            <span class="slot-value">21:59</span>
                        </div>
                        <div class="slot">
                            <span class="slot-label">الوقت إلى</span>
                            <span class="slot-value">21:59</span>
                        </div>
                    </div>
                </div>

                <div class="action-wrap">
                    <button class="action-button" type="button" aria-label="إضافة">
                        +
                    </button>
                </div>
            </section>

            <section class="message-card">
                <label for="message">اكتب رسالة</label>
                <textarea id="message" placeholder="اكتب رسالتك هنا بطريقة واضحة ومختصرة..."></textarea>
            </section>

            <section class="section bottom-actions">
                <p class="secondary-note">
                    تم تحسين المسافات والمحاذاة لتجنب التداخل، مع الحفاظ على شكل مرتب ومريح للعين على الشاشات الصغيرة.
                </p>
                <button class="primary-button" type="button">حفظ التغييرات</button>
            </section>

            <div class="footer-space"></div>
        </div>
    </main>
</body>
</html>
