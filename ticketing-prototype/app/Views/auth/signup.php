<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign up — Universal HelpDesk</title>
    <?= theme_head() ?>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="auth-shell">
        <?= theme_toggle('auth-theme') ?>
        <div class="auth-wrap">
            <div class="auth-brand"><?= brand_mark(34) ?> Universal HelpDesk</div>

            <div class="auth-card">
                <h1>Create your account</h1>
                <div class="subtitle">Submit requests and track them in one place.</div>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-error"><?= icon('flag', 16) ?> <?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif ?>

                <form action="/signup" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="name">Full name</label>
                        <input type="text" id="name" name="name" value="<?= esc(old('name')) ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= esc(old('email')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="8">
                        <div class="field-hint">At least 8 characters.</div>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm password</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                    </div>
                    <button type="submit" class="btn"><?= icon('check', 16) ?> Create account</button>
                </form>

                <div class="auth-switch">Already have an account? <a href="/login">Log in</a></div>
            </div>
        </div>
    </div>
    <?= theme_toggle_script() ?>
</body>
</html>
