<?php
function getPendingVaccinationTasks($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, vaccination_type, animal_type, animal_breed, scheduled_date, scheduled_time FROM vaccination_tasks WHERE user_id = ? AND status = 'pending' ORDER BY scheduled_date, scheduled_time");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$tasks = getPendingVaccinationTasks($pdo, $_SESSION['user_id']);
?>
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-tasks me-2"></i> Pending Vaccination Tasks
    </div>
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <p class="text-center text-muted">No pending vaccination tasks</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($tasks as $task): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($task['vaccination_type']) ?></h6>
                            <small class="text-muted">
                                <?= htmlspecialchars($task['animal_type']) ?> (<?= htmlspecialchars($task['animal_breed']) ?>)
                                - <?= date('M j', strtotime($task['scheduled_date'])) ?> at <?= date('g:i A', strtotime($task['scheduled_time'])) ?>
                            </small>
                        </div>
                        <a href="complete_vaccination.php?task_id=<?= $task['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-check me-1"></i> Complete
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>