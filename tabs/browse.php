<?php
/**
 * Browse Tab - Document List and Search
 */
?>
<div id="browse" class="tab-content">
    <?php if (!empty($message)): ?>
        <div style="background: <?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid <?php echo $messageType === 'success' ? '#28a745' : '#dc3545'; ?>; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 20px;"><?php echo $messageType === 'success' ? '✅' : '❌'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 8px 24px rgba(102,126,234,0.3);">
        <h2 style="color: white; margin: 0; font-size: 28px; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 32px;">📋</span> Browse Documents
            <span style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 14px; font-weight: normal;">
                <?php echo number_format($documentCount); ?> documents
            </span>
        </h2>
        <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 14px;">
            View, search, filter, and manage your collection documents
        </p>
    </div>

    <!-- Search & Filters Section -->
    <?php include __DIR__ . '/browse/search-filters.php'; ?>

    <!-- Bulk Actions Bar -->
    <?php include __DIR__ . '/browse/bulk-actions.php'; ?>

    <!-- Documents Display -->
    <?php include __DIR__ . '/browse/documents-display.php'; ?>

    <!-- Pagination -->
    <?php include __DIR__ . '/browse/pagination.php'; ?>
</div>
