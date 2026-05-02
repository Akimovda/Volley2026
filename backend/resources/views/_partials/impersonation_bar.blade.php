@php
    $impersonatorId = session('impersonator_id');
@endphp
@if($impersonatorId)
<div id="impersonation-bar" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background: #c0392b;
    color: #fff;
    padding: 6px 16px;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
">
    <span>
        👁 Вы просматриваете сайт от имени пользователя
        <strong>{{ auth()->user()?->name }}</strong>
        (ID {{ auth()->id() }})
    </span>
    <form method="POST" action="{{ route('admin.impersonate.leave') }}" style="margin:0">
        @csrf
        <button type="submit" style="
            background: #fff;
            color: #c0392b;
            border: none;
            border-radius: 4px;
            padding: 3px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        ">Вернуться в свой аккаунт</button>
    </form>
</div>
<style>
    body { padding-top: 36px !important; }
</style>
@endif
