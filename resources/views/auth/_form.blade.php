@csrf
<div>
    <label class="brand-label" for="email">Email</label>
    <input class="brand-input" id="email" name="email" type="email" required maxlength="255" autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ is_string(old('email')) ? old('email') : '' }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-error" autofocus>
    <p id="email-error" class="brand-error">@error('email'){{ $message }}@enderror</p>
</div>
<div>
    <label class="brand-label" for="password">Password</label>
    <input class="brand-input" id="password" name="password" type="password" required maxlength="4096" autocomplete="current-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-error">
    <p id="password-error" class="brand-error">@error('password'){{ $message }}@enderror</p>
</div>
