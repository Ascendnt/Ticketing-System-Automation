<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('departments')) {
    /**
     * Canonical list of departments used for agent scoping,
     * ticket routing, and the submission form.
     */
    function departments(): array
    {
        return ['IT', 'HR', 'TQA', 'Reports/WFM', 'Technical', 'Compliance', 'Operations'];
    }
}

if (! function_exists('priorities')) {
    function priorities(): array
    {
        return ['Low', 'Medium', 'High', 'Urgent'];
    }
}

if (! function_exists('ticket_department')) {
    /**
     * The department a ticket is routed to: the AI-classified responsible
     * department, falling back to whatever the requester picked at submission.
     */
    function ticket_department(array $fields): string
    {
        return $fields['Responsible Department'] ?? $fields['Submitting Department'] ?? '';
    }
}

if (! function_exists('resolve_base_department')) {
    /**
     * The AI classifier routes tickets to granular sub-departments
     * ("Technical - Web", "Technical - Salesforce/Tally"), but agent
     * accounts are scoped to one of the primary departments() buckets.
     * Maps a ticket's raw department string back to its primary bucket
     * so agent access/assignment isn't broken by the extra specificity.
     */
    function resolve_base_department(string $ticketDepartment): string
    {
        foreach (departments() as $dept) {
            if (stripos($ticketDepartment, $dept) === 0) {
                return $dept;
            }
        }

        return $ticketDepartment;
    }
}

if (! function_exists('current_user')) {
    /**
     * The logged-in user's session snapshot (id, name, email, role, department), or null.
     */
    function current_user(): ?array
    {
        return session()->get('isLoggedIn') ? session()->get('user') : null;
    }
}

if (! function_exists('brand_mark')) {
    /**
     * Universal HelpDesk logomark: a bold, hard-edged ticket stub with a
     * perforated tear-line and a dot orbiting it (both animated via CSS) —
     * a stamped-paper mark suited to the warm-brutalist identity.
     */
    function brand_mark(int $size = 32): string
    {
        return <<<SVG
        <svg class="brand-mark" width="{$size}" height="{$size}" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g class="stub">
                <path d="M4 9H20V13.4A2.6 2.6 0 0 0 20 18.6V23H4V18.6A2.6 2.6 0 0 0 4 13.4V9Z"
                    fill="#df3f1a" stroke="#17130e" stroke-width="2.2" stroke-linejoin="round"/>
                <path d="M12.2 10.4V21.6" stroke="#fbf6ea" stroke-width="2" stroke-dasharray="2 2.6" stroke-linecap="round"/>
                <rect x="14.6" y="12.4" width="3.4" height="2.1" rx="0.4" fill="#fbf6ea"/>
                <rect x="14.6" y="16" width="3.4" height="2.1" rx="0.4" fill="#fbf6ea"/>
            </g>
            <g class="orbit-dot">
                <rect x="24.4" y="5.4" width="5" height="5" rx="1" fill="#dd9309" stroke="#17130e" stroke-width="1.8"/>
            </g>
        </svg>
        SVG;
    }
}

if (! function_exists('theme_head')) {
    /**
     * Head snippet: font preconnects + a tiny no-flash script that applies the
     * saved (or system-preferred) theme to <html> before first paint.
     * Purely presentational — touches nothing on the server.
     */
    function theme_head(): string
    {
        return <<<HTML
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <script>
        (function () {
            try {
                var saved = localStorage.getItem('uhd-theme');
                var t = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
        </script>
        HTML;
    }
}

if (! function_exists('theme_toggle_script')) {
    /**
     * The click handler that flips and persists the theme. Emitted once per page.
     */
    function theme_toggle_script(): string
    {
        return <<<HTML
        <script>
        (function () {
            function flip() {
                var el = document.documentElement;
                var next = el.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                el.setAttribute('data-theme', next);
                try { localStorage.setItem('uhd-theme', next); } catch (e) {}
            }
            document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
                b.addEventListener('click', flip);
            });
        })();
        </script>
        HTML;
    }
}

if (! function_exists('theme_toggle')) {
    /**
     * The sun/moon toggle button. `$extra` lets a page add positioning classes.
     */
    function theme_toggle(string $extra = ''): string
    {
        $cls = trim('theme-toggle ' . $extra);
        $sun  = icon('sun', 16);
        $moon = icon('moon', 16);
        return <<<HTML
        <button type="button" class="{$cls}" data-theme-toggle aria-label="Toggle dark mode">
            <span class="ico-sun">{$moon}</span>
            <span class="ico-moon">{$sun}</span>
            <span class="lbl"></span>
        </button>
        HTML;
    }
}

if (! function_exists('icon')) {
    /**
     * Small hand-drawn line icons used across the UI (24x24, currentColor strokes).
     */
    function icon(string $name, int $size = 18): string
    {
        $paths = [
            'dashboard' => '<rect x="3.5" y="3.5" width="7.5" height="7.5" rx="2"/><rect x="13" y="3.5" width="7.5" height="7.5" rx="2"/><rect x="3.5" y="13" width="7.5" height="7.5" rx="2"/><rect x="13" y="13" width="7.5" height="7.5" rx="2"/>',
            'ticket' => '<path d="M3.5 8.5c0-1.66 1.34-3 3-3h11c1.66 0 3 1.34 3 3v1.2a1.8 1.8 0 000 3.6v1.2c0 1.66-1.34 3-3 3h-11c-1.66 0-3-1.34-3-3v-1.2a1.8 1.8 0 000-3.6V8.5z"/><path d="M12 6v2M12 16v2M12 10.8v2.4"/>',
            'users' => '<circle cx="8.5" cy="8" r="3"/><path d="M2.8 19c0-3 2.5-5.2 5.7-5.2S14.2 16 14.2 19"/><circle cx="16.5" cy="8.6" r="2.4"/><path d="M15 13.9c2.6.2 4.5 2.2 4.5 5"/>',
            'logout' => '<path d="M9 3.5H6.5c-1.1 0-2 .9-2 2v13c0 1.1.9 2 2 2H9"/><path d="M14 16.5l4.5-4.5-4.5-4.5"/><path d="M18.2 12H9"/>',
            'search' => '<circle cx="10.2" cy="10.2" r="6.2"/><path d="M19 19l-4.3-4.3"/>',
            'send' => '<path d="M3 11l16.5-7.5L13 20l-2.5-6.5L3 11z"/><path d="M10.5 13.5L16 8"/>',
            'chevron-left' => '<path d="M14.5 5l-7 7 7 7"/>',
            'sort' => '<path d="M7 9l4-4 4 4M7 15l4 4 4-4"/>',
            'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
            'flag' => '<path d="M6 3v18"/><path d="M6 4.5h10l-2.5 3.5L16 11.5H6"/>',
            'assign' => '<circle cx="12" cy="8.2" r="3.4"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>',
            'inbox' => '<path d="M3.5 12h4.2l1.6 2.6h5.4l1.6-2.6h4.2"/><path d="M6 6.5h12L20.5 12v6a1.5 1.5 0 01-1.5 1.5H5A1.5 1.5 0 013.5 18v-6L6 6.5z"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
            'lock' => '<rect x="5" y="10.5" width="14" height="9" rx="2"/><path d="M8 10.5V7.5a4 4 0 018 0v3"/>',
            'building' => '<rect x="4" y="3.5" width="16" height="17" rx="1.5"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/>',
            'shield' => '<path d="M12 3.5l7 3v5.2c0 4.6-3 7.9-7 9-4-1.1-7-4.4-7-9V6.5l7-3z"/><path d="M9 12l2 2 4-4.2"/>',
            'check' => '<path d="M4.5 12.5l5 5 10-11"/>',
            'edit' => '<path d="M4 20l.9-3.6L16.4 5 19 7.6 7.5 19.1 4 20z"/>',
            'download' => '<path d="M12 3.5v11.4"/><path d="M7.5 10.5l4.5 4.5 4.5-4.5"/><path d="M4.5 18.5h15"/>',
            'trend' => '<path d="M3.5 16l5-5.5 4 3.5 6.5-7.5"/><path d="M14.5 6.5h4.5v4.5"/>',
            'department' => '<path d="M4 20V6.5a1.5 1.5 0 011.5-1.5h5A1.5 1.5 0 0112 6.5V20"/><path d="M12 11h6.5A1.5 1.5 0 0120 12.5V20"/><path d="M7 9h2M7 13h2M15 15h2"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.5M12 19v2.5M4.4 4.4l1.8 1.8M17.8 17.8l1.8 1.8M2.5 12H5M19 12h2.5M4.4 19.6l1.8-1.8M17.8 6.2l1.8-1.8"/>',
            'moon' => '<path d="M19 14.5A7.5 7.5 0 019.5 5a1 1 0 00-1.3-1.2A8.5 8.5 0 1020.2 15.8 1 1 0 0019 14.5z"/>',
        ];

        $body = $paths[$name] ?? $paths['ticket'];

        return <<<SVG
        <svg width="{$size}" height="{$size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">{$body}</svg>
        SVG;
    }
}
