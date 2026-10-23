{{-- Shared look of the ledenportaal pages (on top of layouts.public). --}}
<style>
    label { font-weight: 600; font-size: .9rem; display: block; margin-bottom: .3rem; }
    input[type=email] { font: inherit; padding: .65rem .75rem; border: 1px solid #c8c8cc; border-radius: .5rem; background: #fff; width: 100%; }
    input:focus { outline: 2px solid var(--accent-dark); outline-offset: 1px; border-color: var(--accent-dark); }
    .button { display: inline-block; font: inherit; font-weight: 700; background: #000; color: var(--accent); border: 0; border-radius: .6rem; padding: .75rem 1.25rem; cursor: pointer; text-decoration: none; text-align: center; }
    .button:hover { background: #222; }
    .button-block { display: block; width: 100%; margin-top: 1rem; }
    .button-light { background: #faf6ee; color: #000; border: 1px solid var(--accent); }
    .button-light:hover { background: #f3ead8; }
    .alert { border-radius: .6rem; padding: .75rem 1rem; margin-bottom: 1rem; }
    .alert-error { background: #fee2e2; color: var(--error); }
    .alert-info { background: #faf6ee; border: 1px solid var(--accent); }
    .error { color: var(--error); font-size: .85rem; display: block; margin-top: .3rem; }
    .portal-nav { display: flex; gap: .4rem; margin-bottom: .75rem; }
    .portal-nav a { flex: 1; text-align: center; padding: .6rem .5rem; border-radius: .6rem; background: #fff; color: var(--ink); text-decoration: none; font-weight: 600; font-size: .95rem; box-shadow: 0 1px 2px rgb(0 0 0 / .05); }
    .portal-nav a[aria-current] { background: #000; color: var(--accent); }
    .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: 1rem; font-size: .85rem; color: var(--muted); }
    .topbar form { margin: 0; }
    .link-button { font: inherit; background: none; border: 0; padding: 0; color: var(--accent-dark); text-decoration: underline; cursor: pointer; }
</style>
