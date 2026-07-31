<?= $this->extend('templates/layout') ?>

<?= $this->section('content') ?>

<div class="page-header">
    <div>
        <h1>Submit a New Ticket</h1>
        <div class="subtitle">We'll classify it automatically and route it to the right team.</div>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-error"><?= icon('flag', 16) ?> <?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="form-card">
    <form action="/tickets" method="post">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="requester_name">Requester Name</label>
                <input type="text" id="requester_name" value="<?= esc($user['name']) ?>" disabled>
            </div>

            <div class="form-group">
                <label for="requester_email">Requester Email</label>
                <input type="email" id="requester_email" value="<?= esc($user['email']) ?>" disabled>
            </div>

            <div class="form-group span-2">
                <label for="submitting_department">Submitting Department</label>
                <select id="submitting_department" name="submitting_department" required>
                    <option value="">Select a department...</option>
                    <?php foreach (departments() as $dept): ?>
                        <option value="<?= esc($dept) ?>"><?= esc($dept) ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="form-group span-2">
                <label for="request_description">Describe your request</label>
                <textarea id="request_description" name="request_description" rows="6" required placeholder="Describe your request in your own words. Include what you need and any deadline."></textarea>
            </div>
        </div>

        <button type="submit" class="btn"><?= icon('send', 16) ?> Submit Ticket</button>
    </form>
</div>

<?= $this->endSection() ?>
