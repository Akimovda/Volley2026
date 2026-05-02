@php
    $impersonatorId = session('impersonator_id');
@endphp
@if($impersonatorId)
<div id="impersonation-bar" style="
border-radius: 1.6rem 1.6rem 0 0;
    background: #c0392b;
    color: #fff;
    padding: .6rem 2rem;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
	
    box-shadow: 0 .2rem .8rem rgba(0,0,0,.3);
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
.top-section {
       padding: 15rem 0 1rem!important;
}	
@media (max-width: 767px) {
    .top-section {
        padding: 20rem 0 2rem!important;
    }
}	
	

</style>
@endif
