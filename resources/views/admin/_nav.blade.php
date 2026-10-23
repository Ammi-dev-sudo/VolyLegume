<nav class="mb-5 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('admin.dashboard') }}"
       class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-emerald-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
        Supervision
    </a>
    <a href="{{ route('admin.users.index') }}"
       class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('admin.users.*') ? 'bg-emerald-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
        Comptes &amp; rôles
    </a>
    <a href="{{ route('admin.farms.index') }}"
       class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('admin.farms.*') ? 'bg-emerald-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
        Exploitations
    </a>
    <a href="{{ route('admin.audit.index') }}"
       class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('admin.audit.*') ? 'bg-emerald-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
        Journal global
    </a>
</nav>
