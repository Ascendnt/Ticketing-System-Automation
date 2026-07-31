<?= $this->extend('templates/layout') ?>

<?= $this->section('content') ?>

<a href="/admin/users" class="back-link"><?= icon('chevron-left', 14) ?> Back to accounts</a>

<div class="page-header">
    <div>
        <h1>New Account</h1>
        <div class="subtitle">Create a staff or requester login.</div>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-error"><?= icon('flag', 16) ?> <?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="form-card narrow">
    <form action="/admin/users" method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= esc(old('name')) ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= esc(old('email')) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Temporary password</label>
            <input type="password" id="password" name="password" required minlength="8">
            <div class="field-hint">At least 8 characters. Share this securely — they can change it later.</div>
        </div>

        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" onchange="document.getElementById('dept-group').style.display = this.value === 'agent' ? 'block' : 'none'">
                <option value="requester">Requester</option>
                <option value="agent">Agent (department-scoped)</option>
                <option value="superadmin">Superadmin (sees everything)</option>
            </select>
        </div>

        <div class="form-group" id="dept-group" style="display:none">
            <label for="department">Department</label>
            <select id="department" name="department">
                <option value="">Select a department...</option>
                <?php foreach (departments() as $dept): ?>
                    <option value="<?= esc($dept) ?>"><?= esc($dept) ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <button type="submit" class="btn"><?= icon('check', 16) ?> Create account</button>
    </form>
</div>

<?= $this->endSection() ?>
