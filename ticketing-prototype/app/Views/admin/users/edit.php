<?= $this->extend('templates/layout') ?>

<?= $this->section('content') ?>

<a href="/admin/users" class="back-link"><?= icon('chevron-left', 14) ?> Back to accounts</a>

<div class="page-header">
    <div>
        <h1><?= esc($editUser['name']) ?></h1>
        <div class="subtitle"><?= esc($editUser['email']) ?></div>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-error"><?= icon('flag', 16) ?> <?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="form-card narrow">
    <form action="/admin/users/<?= $editUser['id'] ?>" method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= esc($editUser['name']) ?>" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" value="<?= esc($editUser['email']) ?>" disabled>
            <div class="field-hint">Email can't be changed here.</div>
        </div>

        <div class="form-group">
            <label for="password">Reset password</label>
            <input type="password" id="password" name="password" minlength="8" placeholder="Leave blank to keep current password">
        </div>

        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" onchange="document.getElementById('dept-group').style.display = this.value === 'agent' ? 'block' : 'none'">
                <?php foreach (['requester' => 'Requester', 'agent' => 'Agent (department-scoped)', 'superadmin' => 'Superadmin (sees everything)'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $editUser['role'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="form-group" id="dept-group" style="display:<?= $editUser['role'] === 'agent' ? 'block' : 'none' ?>">
            <label for="department">Department</label>
            <select id="department" name="department">
                <option value="">Select a department...</option>
                <?php foreach (departments() as $dept): ?>
                    <option value="<?= esc($dept) ?>" <?= $editUser['department'] === $dept ? 'selected' : '' ?>><?= esc($dept) ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="form-group checkbox-row">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $editUser['is_active'] === 1 ? 'checked' : '' ?>>
            <label for="is_active">Account active</label>
        </div>

        <button type="submit" class="btn"><?= icon('check', 16) ?> Save changes</button>
    </form>
</div>

<?= $this->endSection() ?>
