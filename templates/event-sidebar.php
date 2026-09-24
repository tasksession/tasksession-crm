<?php
/* Event Sidebar Template */
?>
<div id="event-sidebar" class="task-sidebar d-flex">
  <div class="task-detail-area">
    <div class="task-sidebar-header d-flex align-items-center col-gap-10" style="z-index:99;">
      <button id="close-event-sidebar" class="close-btn" type="button" aria-label="Close">
        <?php echo ts_icon('close', 'w-6'); ?>
      </button>
      <h2 class="mb-0 flex-grow"><?php echo $lang['Event Details'] ?? 'Event Details'; ?></h2>
      <div class="dropdown">
        <button class="btn-dots" type="button" data-bs-toggle="dropdown">
          <?php echo ts_icon('dots-vertical', 'w-6'); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item d-flex align-items-center" href="#" id="event-sidebar-edit-link"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Event'] ?? 'Edit Event'; ?></a></li>
          <li><a class="dropdown-item d-flex align-items-center text-danger" href="#" id="event-sidebar-delete-link"><?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Event'] ?? 'Delete Event'; ?></a></li>
        </ul>
      </div>
    </div>

    <div class="task-sidebar-content scroll-bar p-4">
      <div id="event-loading"><?php echo $lang['Loading...'] ?? 'Loading...'; ?></div>
      <div id="event-error" class="alert alert-danger" style="display:none;"></div>
      <div id="event-content" style="display:none;">
        <h3 id="event-title-display"></h3>
        <div class="mb-3">
          <div class="meta-row">
            <span class="meta-label"><?php echo $lang['Starts']; ?></span>
            <span id="event-start-display" class="meta-value">-</span>
            <span class="meta-arrow">→</span>
            <span class="meta-label"><?php echo $lang['Due']; ?></span>
            <span id="event-end-display" class="meta-value">-</span>
          </div>
        </div>
        <div class="mb-3">
          <span class="title-head"><?php echo $lang['Location']; ?></span>
          <div id="event-location-display"></div>
        </div>
        <div class="mb-3">
          <span class="title-head"><?php echo $lang['Participants'] ?? 'Participants'; ?></span>
          <div id="event-participants-display" class="task-team mt-2"></div>
        </div>
        <div>
          <span class="title-head"><?php echo $lang['Description']; ?></span>
          <div id="event-description-display" class="description-content mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>
