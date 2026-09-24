(function () {
  "use strict";

  function tasksessionLocalSanitizeHtml(html) {
    if (typeof window.tasksessionSanitizeHtml === 'function') {
      return window.tasksessionSanitizeHtml(html);
    }
    if (html == null) return '';
    html = String(html);
    var wrap = document.createElement('div');
    wrap.innerHTML = html;
    var blocked = { SCRIPT: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, LINK: 1, META: 1, BASE: 1, FORM: 1 };
    var walk = wrap.querySelectorAll('*');
    for (var i = walk.length - 1; i >= 0; i--) {
      var el = walk[i];
      if (blocked[el.tagName]) {
        if (el.parentNode) el.parentNode.removeChild(el);
        continue;
      }
      if (el.attributes) {
        for (var a = el.attributes.length - 1; a >= 0; a--) {
          var name = el.attributes[a].name;
          var val = el.attributes[a].value || '';
          if (/^on/i.test(name) || /javascript:/i.test(val)) {
            el.removeAttribute(name);
          }
        }
      }
    }
    return wrap.innerHTML;
  }

  const modalEl = document.getElementById("calendarEventModal");
  const eventForm = document.getElementById("calendarEventForm");
  const openModalBtn = document.getElementById("openAddEventModal");
  const openModalBtnMobile = document.getElementById("openAddEventModalMobile");
  const eventModalTitle = document.getElementById("calendarEventModalLabel");
  const eventSubmitBtn = document.getElementById("calendarEventSubmitBtn");
  const dropzones = Array.from(document.querySelectorAll(".calendar-dropzone"));
  const waitingZone = document.querySelector(".waiting-dropzone");
  const boardWrap = document.querySelector(".calendar-board-wrap");
  const daysBoard = document.getElementById("calendarDaysBoard");
  const csrfToken = (window.calendarPageData && window.calendarPageData.csrfToken) || "";
  let draggedCard = null;
  let boardFrozen = false;
  let frozenBoardLeft = 0;
  let frozenWindowX = 0;
  let calendarDragPreviewEl = null;
  let calendarDragSourceCard = null;
  let calendarDragPointerOffsetX = 0;
  let calendarDragPointerOffsetY = 0;
  let calendarEmptyDragCanvas = null;
  let calendarDropSlotEl = null;
  let calendarDraggedSlotHeight = 120;
  let calendarDropSlotZone = null;
  let calendarDropSlotBeforeKey = null;
  let calendarDragSourceDate = "";
  let calendarDragSourceWaiting = false;

  function initEventParticipantsPicker() {
    const users = Array.isArray(window.calendarParticipantUsers) ? window.calendarParticipantUsers : [];
    const menu = document.getElementById("eventParticipantsDropdownMenu");
    const optionsList = document.getElementById("eventParticipantsOptionsList");
    const searchInput = document.getElementById("eventParticipantsSearchInput");
    const noParticipantsFound = document.getElementById("noParticipantsFound");
    const btnText = document.getElementById("eventParticipantsDropdownBtnText");
    const participantsInput = document.getElementById("calendar_participants");
    if (!menu || !optionsList || !btnText || !participantsInput) return;

    let selected = [];

    if (!optionsList.dataset.built) {
      optionsList.innerHTML = "";
      users.forEach((u) => {
        const li = document.createElement("li");
        const searchText = `${u.name || ""} ${u.role || ""}`.toLowerCase();
        li.className = "project-option-item participant-option-item";
        li.setAttribute("data-search-text", searchText);
        li.innerHTML = `
          <a href="#" class="dropdown-item project-option d-flex align-items-center" data-id="${u.id}">
            <div class="me-2">${u.image || ""}</div>
            <span>${u.name}</span>
            <span class="badge color-inprogress inprogress-bg-op ms-2">${u.role || ""}</span>
          </a>
        `;
        optionsList.appendChild(li);
      });
      optionsList.dataset.built = "1";
    }

    function updateSelectedText() {
      if (!selected.length) {
        btnText.textContent = "Add participants";
        participantsInput.value = "";
        return;
      }
      const names = selected.map((x) => x.name);
      btnText.textContent = names.length <= 2 ? names.join(", ") : `${names[0]}, ${names[1]} +${names.length - 2}`;
      participantsInput.value = names.join(", ");
    }

    optionsList.onclick = function (e) {
      e.preventDefault();
      e.stopPropagation();
      const a = e.target.closest("a[data-id]");
      if (!a) return;

      const id = String(a.getAttribute("data-id"));
      const user = users.find((x) => String(x.id) === id);
      if (!user) return;

      const idx = selected.findIndex((x) => String(x.id) === id);
      if (idx === -1) {
        selected.push({ id: user.id, name: user.name });
        a.classList.add("active");
      } else {
        selected.splice(idx, 1);
        a.classList.remove("active");
      }
      updateSelectedText();
    };

    if (searchInput && !searchInput.dataset.bound) {
      searchInput.addEventListener("click", function (e) {
        e.stopPropagation();
      });
      searchInput.addEventListener("input", function () {
        const query = (searchInput.value || "").trim().toLowerCase();
        const items = Array.from(optionsList.querySelectorAll(".participant-option-item"));
        let visibleCount = 0;
        items.forEach((item) => {
          const hay = (item.getAttribute("data-search-text") || "").toLowerCase();
          const show = query === "" || hay.includes(query);
          item.style.display = show ? "" : "none";
          if (show) visibleCount++;
        });
        if (noParticipantsFound) {
          noParticipantsFound.style.display = visibleCount === 0 ? "block" : "none";
        }
      });
      searchInput.dataset.bound = "1";
    }

    updateSelectedText();
  }

  function initModal() {
    if (!modalEl || !eventForm) return;
    initEventParticipantsPicker();

    function setEventModalMode(mode) {
      const isEdit = mode === "edit";
      const createLabel = (eventSubmitBtn && eventSubmitBtn.dataset.createLabel) || "Create event";
      const updateLabel = (eventSubmitBtn && eventSubmitBtn.dataset.updateLabel) || "Update event";
      if (eventSubmitBtn) {
        eventSubmitBtn.textContent = isEdit ? updateLabel : createLabel;
      }
      if (eventModalTitle) {
        eventModalTitle.textContent = isEdit ? updateLabel : createLabel;
      }
    }

    function getModalInstance() {
      if (!window.bootstrap || !window.bootstrap.Modal) return null;
      return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    modalEl.addEventListener("hidden.bs.modal", function () {
      var stillOpen = document.querySelectorAll(".modal.show").length;
      if (stillOpen === 0) {
        document.querySelectorAll(".modal-backdrop").forEach(function (node) {
          if (node && node.parentNode) {
            node.parentNode.removeChild(node);
          }
        });
        document.body.classList.remove("modal-open");
        document.body.style.removeProperty("overflow");
        document.body.style.removeProperty("padding-right");
      }
    });

    if (openModalBtn) {
      openModalBtn.addEventListener("click", function (e) {
        e.preventDefault();
        const mode = openModalBtn.dataset.mode || "create";
        let hiddenEventId = eventForm.querySelector('input[name="event_id"]');
        if (mode === "create" && hiddenEventId) {
          hiddenEventId.value = "";
        }
        setEventModalMode(mode);
        const dateInput = document.getElementById("calendar_event_date");
        const isEditMode = !!(hiddenEventId && hiddenEventId.value);
        if (!isEditMode && dateInput && window.calendarPageData && window.calendarPageData.selectedDate) {
          dateInput.value = window.calendarPageData.selectedDate;
        }
        const modal = getModalInstance();
        if (modal) {
          modal.show();
        }
        openModalBtn.dataset.mode = "create";
      });
    }

    if (openModalBtnMobile) {
      openModalBtnMobile.addEventListener("click", function (e) {
        e.preventDefault();
        if (openModalBtn) {
          openModalBtn.dataset.mode = "create";
          openModalBtn.click();
        }
      });
    }

    eventForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const fd = new FormData(eventForm);
      if (csrfToken) fd.append("csrf_token", csrfToken);
      fetch("../ajax/calendar_event_save.php", {
        method: "POST",
        body: fd,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then((r) => r.json())
        .then((json) => {
          if (!json || json.status !== "ok") {
            throw new Error((json && json.message) || "save_failed");
          }
          const modal = getModalInstance();
          if (modal) {
            modal.hide();
          }
          window.location.reload();
        })
        .catch(() => {
          const text =
            (window.calendarPageData &&
              window.calendarPageData.lang &&
              window.calendarPageData.lang.saveError) ||
            "Something went wrong. Please try again.";
          alert(text);
        });
    });
  }

  function formatDateTimeDisplay(value) {
    if (!value) return "-";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString();
  }

  window.openEventSidebar = function (eventId) {
    const sidebar = document.getElementById("event-sidebar");
    const loading = document.getElementById("event-loading");
    const content = document.getElementById("event-content");
    const error = document.getElementById("event-error");
    if (!sidebar || !loading || !content || !error) return;

    sidebar.dataset.eventId = String(eventId);
    // Prevent immediate close from same click event.
    try { window.eventSidebarPreventCloseUntil = Date.now() + 300; } catch (_) {}
    sidebar.classList.add("open");
    loading.style.display = "block";
    content.style.display = "none";
    error.style.display = "none";
    error.textContent = "";

    fetch(`../includes/event_details.php?id=${encodeURIComponent(eventId)}`, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((r) => r.json())
      .then((json) => {
        if (!json || json.status !== "ok" || !json.event) {
          throw new Error("load_failed");
        }
        document.getElementById("event-title-display").textContent = json.event.title || "Event";
        document.getElementById("event-start-display").textContent = formatDateTimeDisplay(json.event.start_datetime || json.event.event_date);
        document.getElementById("event-end-display").textContent = formatDateTimeDisplay(json.event.end_datetime || "");
        document.getElementById("event-location-display").textContent = json.event.location_label || "-";
        document.getElementById("event-description-display").innerHTML = json.event.description ? tasksessionLocalSanitizeHtml(json.event.description) : "-";

        const participantsWrap = document.getElementById("event-participants-display");
        if (participantsWrap) {
          participantsWrap.innerHTML = "";
          const participants = Array.isArray(json.participants) ? json.participants : [];
          participants.forEach((p, idx) => {
            const wrap = document.createElement("div");
            wrap.className = "avatar-overlap";
            wrap.setAttribute("data-bs-toggle", "tooltip");
            wrap.setAttribute("data-bs-placement", "top");
            wrap.title = p.name || "User";
            if (p.avatar_html) {
              wrap.innerHTML = p.avatar_html;
            } else {
              const initials = (p.name || "U").trim().charAt(0).toUpperCase();
              wrap.innerHTML = `<div class="avatar-initials color-${(idx % 8) + 1} avatar-initials-small rounded-circle">${initials}</div>`;
            }
            participantsWrap.appendChild(wrap);
            if (typeof bootstrap !== "undefined" && typeof bootstrap.Tooltip !== "undefined") {
              new bootstrap.Tooltip(wrap, { container: "body", trigger: "hover", placement: "top" });
            }
          });
        }

        loading.style.display = "none";
        content.style.display = "block";
      })
      .catch(() => {
        loading.style.display = "none";
        error.style.display = "block";
        error.textContent = "Failed to load event details";
      });
  };

  window.openEventFromCalendar = function (e, eventId) {
    if (!e) return;
    const target = e.target;
    if (
      target.closest(".dropdown") ||
      target.closest("a") ||
      target.closest("button") ||
      target.closest("input") ||
      target.closest("textarea")
    ) {
      return;
    }
    window.openEventSidebar(eventId);
  };

  window.editEventFromCard = function (eventId) {
    fetch(`../includes/event_details.php?id=${encodeURIComponent(eventId)}`, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((r) => r.json())
      .then((json) => {
        if (!json || json.status !== "ok" || !json.event) {
          throw new Error("load_failed");
        }
        const ev = json.event;
        const eventIdInputName = "event_id";
        let hiddenEventId = eventForm.querySelector(`input[name="${eventIdInputName}"]`);
        if (!hiddenEventId) {
          hiddenEventId = document.createElement("input");
          hiddenEventId.type = "hidden";
          hiddenEventId.name = eventIdInputName;
          eventForm.appendChild(hiddenEventId);
        }
        hiddenEventId.value = String(ev.id || eventId);
        const title = document.getElementById("calendar_event_title");
        const date = document.getElementById("calendar_event_date");
        const start = document.getElementById("calendar_start_time");
        const end = document.getElementById("calendar_end_time");
        const location = document.getElementById("calendar_location");
        const team = document.getElementById("calendar_team_label");
        const desc = document.getElementById("calendar_description");
        if (title) title.value = ev.title || "";
        if (date) date.value = ev.event_date || "";
        if (start) start.value = ev.start_datetime ? String(ev.start_datetime).slice(11, 16) : "";
        if (end) end.value = ev.end_datetime ? String(ev.end_datetime).slice(11, 16) : "";
        if (location) location.value = ev.location_label || "";
        if (team) team.value = ev.team_label || "";
        if (desc) desc.value = ev.description || "";
        if (typeof openModalBtn?.click === "function") {
          openModalBtn.dataset.mode = "edit";
          openModalBtn.click();
        }
      })
      .catch(() => {
        alert("Unable to load event for edit");
      });
  };

  window.deleteEventFromCard = function (eventId) {
    const ok = window.confirm("Delete this event?");
    if (!ok) return;
    const payload = new URLSearchParams();
    payload.append("event_id", String(eventId));
    if (csrfToken) payload.append("csrf_token", csrfToken);
    fetch("../ajax/calendar_event_delete.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: payload.toString(),
    })
      .then((r) => r.json())
      .then((json) => {
        if (!json || json.status !== "ok") throw new Error("delete_failed");
        window.location.reload();
      })
      .catch(() => {
        alert("Unable to delete event");
      });
  };

  function getCalendarEmptyDragCanvas() {
    if (!calendarEmptyDragCanvas) {
      calendarEmptyDragCanvas = document.createElement("canvas");
      calendarEmptyDragCanvas.width = 1;
      calendarEmptyDragCanvas.height = 1;
    }
    return calendarEmptyDragCanvas;
  }

  function ensureCalendarDropSlotLabel(slot) {
    let lbl = slot.querySelector(".calendar-drop-slot-label");
    if (!lbl) {
      lbl = document.createElement("span");
      lbl.className = "calendar-drop-slot-label";
      lbl.textContent = "Drop here";
      slot.appendChild(lbl);
    }
  }

  function getCalendarDropSlot() {
    if (!calendarDropSlotEl) {
      calendarDropSlotEl = document.createElement("div");
      calendarDropSlotEl.className = "calendar-drop-slot";
      calendarDropSlotEl.setAttribute("aria-hidden", "true");
      ensureCalendarDropSlotLabel(calendarDropSlotEl);
    }
    return calendarDropSlotEl;
  }

  function disposeCalendarDropSlot() {
    calendarDropSlotZone = null;
    calendarDropSlotBeforeKey = null;
    if (calendarDropSlotEl && calendarDropSlotEl.parentNode) {
      calendarDropSlotEl.parentNode.removeChild(calendarDropSlotEl);
    }
  }

  function calendarDragFloatMove(ev) {
    if (!calendarDragPreviewEl) return;
    if (typeof ev.clientX !== "number" || typeof ev.clientY !== "number") return;
    if (ev.clientX === 0 && ev.clientY === 0) return;
    calendarDragPreviewEl.style.left = ev.clientX - calendarDragPointerOffsetX + "px";
    calendarDragPreviewEl.style.top = ev.clientY - calendarDragPointerOffsetY + "px";
  }

  function disposeCalendarDragPreview() {
    if (calendarDragSourceCard) {
      calendarDragSourceCard.removeEventListener("drag", calendarDragFloatMove);
      calendarDragSourceCard = null;
    }
    if (calendarDragPreviewEl && calendarDragPreviewEl.parentNode) {
      calendarDragPreviewEl.parentNode.removeChild(calendarDragPreviewEl);
    }
    calendarDragPreviewEl = null;
  }

  function isCalendarLayoutCard(el) {
    return !!(
      el &&
      el.classList &&
      el.classList.contains("calendar-item-card") &&
      el.dataset &&
      el.dataset.itemType &&
      el.dataset.itemId
    );
  }

  function prevCalendarCardKey(cardEl) {
    let el = cardEl.previousElementSibling;
    while (el) {
      if (el.classList && el.classList.contains("calendar-drop-slot")) {
        el = el.previousElementSibling;
        continue;
      }
      if (isCalendarLayoutCard(el)) {
        return el.dataset.itemType + ":" + el.dataset.itemId;
      }
      el = el.previousElementSibling;
    }
    return "";
  }

  function nextCalendarCardKey(cardEl) {
    let el = cardEl.nextElementSibling;
    while (el) {
      if (el.classList && el.classList.contains("calendar-drop-slot")) {
        el = el.nextElementSibling;
        continue;
      }
      if (isCalendarLayoutCard(el)) {
        return el.dataset.itemType + ":" + el.dataset.itemId;
      }
      el = el.nextElementSibling;
    }
    return "";
  }

  function calendarCardNeighborKeys(cardEl) {
    return {
      insertBeforeKey: nextCalendarCardKey(cardEl),
      insertAfterKey: prevCalendarCardKey(cardEl),
    };
  }

  function updateCalendarDropSlot(zone, clientY) {
    if (!zone || typeof clientY !== "number") return;
    const slot = getCalendarDropSlot();
    const h = calendarDraggedSlotHeight || 120;
    slot.style.height = h + "px";
    slot.style.minHeight = h + "px";
    ensureCalendarDropSlotLabel(slot);

    const cards = Array.from(zone.querySelectorAll(".calendar-item-card:not(.dragging)"));
    let insertBefore = null;
    for (let i = 0; i < cards.length; i++) {
      const rect = cards[i].getBoundingClientRect();
      const mid = rect.top + rect.height / 2;
      if (clientY < mid) {
        insertBefore = cards[i];
        break;
      }
    }

    const loadMore = zone.querySelector(".calendar-load-more-container");
    const beforeKey = insertBefore ? insertBefore.dataset.itemType + ":" + insertBefore.dataset.itemId : "__end__";
    if (calendarDropSlotZone === zone && calendarDropSlotBeforeKey === beforeKey && slot.parentNode === zone) {
      return;
    }
    calendarDropSlotZone = zone;
    calendarDropSlotBeforeKey = beforeKey;

    if (slot.parentNode) {
      slot.parentNode.removeChild(slot);
    }
    if (insertBefore) {
      zone.insertBefore(slot, insertBefore);
    } else if (loadMore) {
      zone.insertBefore(slot, loadMore);
    } else {
      zone.appendChild(slot);
    }
  }

  function persistDrop(itemType, itemId, targetDate, isWaiting, meta) {
    const payload = new URLSearchParams();
    payload.append("item_type", itemType);
    payload.append("item_id", itemId);
    payload.append("target_date", targetDate || "");
    payload.append("is_waiting_list", isWaiting ? "1" : "0");
    if (meta) {
      if (meta.insertBeforeKey) payload.append("insert_before_key", meta.insertBeforeKey);
      if (meta.insertAfterKey) payload.append("insert_after_key", meta.insertAfterKey);
      if (typeof meta.position === "number") payload.append("position", String(meta.position));
      if (meta.sourceDate) payload.append("source_date", meta.sourceDate);
      payload.append("source_is_waiting", meta.sourceIsWaiting ? "1" : "0");
    }
    if (csrfToken) payload.append("csrf_token", csrfToken);

    return fetch("../ajax/calendar_drag_update.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: payload.toString(),
    }).then((r) => r.json());
  }

  function handleDrop(zone, card) {
    if (!zone || !card) return;

    const slotEl = zone.querySelector(".calendar-drop-slot");
    const loadMore = zone.querySelector(".calendar-load-more-container");
    if (slotEl && slotEl.parentNode === zone) {
      zone.insertBefore(card, slotEl);
      slotEl.remove();
    } else if (loadMore) {
      zone.insertBefore(card, loadMore);
    } else {
      zone.appendChild(card);
    }
    disposeCalendarDropSlot();

    const itemType = card.dataset.itemType || "task";
    const itemId = card.dataset.itemId || "";
    const targetDate = zone.dataset.date || "";
    const isWaiting = !!zone.closest(".calendar-waiting-list");

    const cards = Array.from(zone.querySelectorAll(".calendar-item-card"));
    const position = cards.indexOf(card);
    const neighbors = calendarCardNeighborKeys(card);

    const meta = {
      insertBeforeKey: neighbors.insertBeforeKey,
      insertAfterKey: neighbors.insertAfterKey,
      position: position >= 0 ? position : 0,
      sourceDate: calendarDragSourceWaiting ? "" : calendarDragSourceDate || "",
      sourceIsWaiting: calendarDragSourceWaiting,
    };

    persistDrop(itemType, itemId, targetDate, isWaiting, meta).then((json) => {
      if (!json || json.status !== "ok") {
        window.location.reload();
        return;
      }
      dedupeCards(zone);
    }).catch(() => window.location.reload());
  }

  function dedupeCards(zone) {
    if (!zone) return;
    const cards = Array.from(zone.querySelectorAll(".calendar-item-card[data-item-type][data-item-id]"));
    const seen = new Set();
    // keep last card (recently dropped) and remove older duplicates
    for (let i = cards.length - 1; i >= 0; i--) {
      const c = cards[i];
      const key = `${c.dataset.itemType}:${c.dataset.itemId}`;
      if (seen.has(key)) {
        c.remove();
      } else {
        seen.add(key);
      }
    }
  }

  function enforceFrozenBoardScroll() {
    if (!boardFrozen) return;
    if (boardWrap && boardWrap.scrollLeft !== frozenBoardLeft) {
      boardWrap.scrollLeft = frozenBoardLeft;
    }
    if (window.scrollX !== frozenWindowX) {
      window.scrollTo(frozenWindowX, window.scrollY);
    }
  }

  function freezeBoardScroll() {
    if (boardFrozen) return;
    boardFrozen = true;
    frozenBoardLeft = boardWrap ? boardWrap.scrollLeft : 0;
    frozenWindowX = window.scrollX;
    document.body.classList.add("calendar-board-frozen");
    document.body.classList.add("calendar-drag-active");
    if (boardWrap) {
      boardWrap.classList.add("drag-scroll-lock", "drag-scroll-persist");
    }
    if (daysBoard) {
      daysBoard.classList.add("drag-scroll-lock", "drag-scroll-persist");
    }
    if (boardWrap) {
      boardWrap.addEventListener("scroll", enforceFrozenBoardScroll);
    }
    window.addEventListener("scroll", enforceFrozenBoardScroll);
  }

  function unfreezeBoardScroll() {
    if (!boardFrozen) return;
    boardFrozen = false;
    document.body.classList.remove("calendar-board-frozen");
    document.body.classList.remove("calendar-drag-active");
    if (boardWrap) {
      boardWrap.classList.remove("drag-scroll-lock", "drag-scroll-persist");
      boardWrap.removeEventListener("scroll", enforceFrozenBoardScroll);
    }
    if (daysBoard) {
      daysBoard.classList.remove("drag-scroll-lock", "drag-scroll-persist");
    }
    window.removeEventListener("scroll", enforceFrozenBoardScroll);
  }

  function preventBoardWheelWhileFrozen(e) {
    if (!boardFrozen) return;
    e.preventDefault();
  }

  function clearColumnDropIndicators() {
    document.querySelectorAll(".calendar-day-column.dropping-over").forEach((col) => {
      col.classList.remove("dropping-over");
    });
    document.querySelectorAll(".calendar-dropzone.drag-over, .waiting-dropzone.drag-over").forEach((z) => {
      z.classList.remove("drag-over");
    });
  }

  function bindCardDragEvents(card) {
    if (!card || card.dataset.dragBound === "1") return;
    card.dataset.dragBound = "1";
    card.addEventListener("dragstart", function (e) {
      draggedCard = card;
      freezeBoardScroll();

      const srcZone = card.closest(".calendar-dropzone, .waiting-dropzone");
      calendarDragSourceDate = srcZone && srcZone.dataset ? srcZone.dataset.date || "" : "";
      calendarDragSourceWaiting = !!(srcZone && srcZone.closest(".calendar-waiting-list"));

      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = "move";
        const itemType = card.dataset.itemType || "task";
        const itemId = card.dataset.itemId || "";
        e.dataTransfer.setData("text/plain", itemType + ":" + itemId);
        e.dataTransfer.setData("text", itemId);

        disposeCalendarDragPreview();
        disposeCalendarDropSlot();
        calendarDraggedSlotHeight = Math.max(card.offsetHeight || 0, 80);

        const w = card.offsetWidth;
        const h = card.offsetHeight;
        const offsetX = Math.max(16, Math.min(Math.round(w / 2), w));
        const offsetY = Math.max(16, Math.min(Math.round(h / 2), h));

        const dragPreview = card.cloneNode(true);
        dragPreview.classList.remove("dragging");
        dragPreview.style.position = "fixed";
        dragPreview.style.left = e.clientX - offsetX + "px";
        dragPreview.style.top = e.clientY - offsetY + "px";
        dragPreview.style.width = w + "px";
        dragPreview.style.margin = "0";
        dragPreview.style.zIndex = "2147483647";
        dragPreview.style.pointerEvents = "none";
        dragPreview.style.opacity = "1";
        dragPreview.style.background = "#ffffff";
        dragPreview.style.boxShadow = "0 18px 45px rgba(0, 0, 0, 0.22)";
        dragPreview.style.transformOrigin = "center center";
        dragPreview.style.transition = "transform 220ms cubic-bezier(0.33, 1, 0.68, 1)";
        dragPreview.style.transform = "rotate(0deg) scale(1.045)";
        dragPreview.style.filter = "none";
        dragPreview.style.webkitFontSmoothing = "antialiased";
        dragPreview.classList.add("calendar-drag-preview");
        document.body.appendChild(dragPreview);
        void dragPreview.offsetWidth;
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            if (!dragPreview.parentNode) return;
            dragPreview.style.transform = "rotate(3.93deg) scale(1.045)";
          });
        });

        if (typeof e.dataTransfer.setDragImage === "function") {
          e.dataTransfer.setDragImage(getCalendarEmptyDragCanvas(), 0, 0);
        }
        calendarDragPointerOffsetX = offsetX;
        calendarDragPointerOffsetY = offsetY;
        calendarDragSourceCard = card;
        card.addEventListener("drag", calendarDragFloatMove);
        calendarDragPreviewEl = dragPreview;
      }
      card.classList.add("dragging");
    });
    card.addEventListener("dragend", function () {
      disposeCalendarDragPreview();
      disposeCalendarDropSlot();
      card.classList.remove("dragging");
      draggedCard = null;
      clearColumnDropIndicators();
    });
  }

  function initDragAndDrop() {
    const cards = Array.from(document.querySelectorAll(".calendar-item-card"));
    cards.forEach((card) => {
      bindCardDragEvents(card);
    });

    const zoneSet = new Set(Array.from(document.querySelectorAll(".calendar-dropzone")));
    if (waitingZone) {
      zoneSet.add(waitingZone);
    }
    const dragZones = Array.from(zoneSet);

    dragZones.forEach((zone) => {
      zone.addEventListener("dragover", function (e) {
        e.preventDefault();
        if (!draggedCard) return;
        const col = zone.closest(".calendar-day-column");
        clearColumnDropIndicators();
        if (col) col.classList.add("dropping-over");
        zone.classList.add("drag-over");
        if (typeof e.clientY === "number") {
          updateCalendarDropSlot(zone, e.clientY);
        }
      });

      zone.addEventListener("dragleave", function (e) {
        if (zone.contains(e.relatedTarget)) return;
        zone.classList.remove("drag-over");
        const col = zone.closest(".calendar-day-column");
        if (col) col.classList.remove("dropping-over");
        disposeCalendarDropSlot();
      });

      zone.addEventListener("drop", function (e) {
        e.preventDefault();
        zone.classList.remove("drag-over");
        clearColumnDropIndicators();
        handleDrop(zone, draggedCard);
      });
    });

    if (boardWrap) {
      boardWrap.addEventListener("wheel", preventBoardWheelWhileFrozen, { passive: false });
      boardWrap.addEventListener("touchmove", preventBoardWheelWhileFrozen, { passive: false });
    }

    dropzones.forEach((zone) => dedupeCards(zone));
  }

  function initCalendarLoadMore() {
    document.addEventListener("click", function (e) {
      const btn = e.target.closest(".calendar-load-more-btn");
      if (!btn) return;
      e.preventDefault();

      const zone = btn.closest(".calendar-dropzone");
      const container = btn.closest(".calendar-load-more-container");
      if (!zone || !container) return;

      const date = btn.dataset.date || zone.dataset.date || "";
      const offset = parseInt(btn.dataset.offset || "20", 10) || 20;
      const limit = parseInt(btn.dataset.limit || "20", 10) || 20;
      const spinner = btn.querySelector(".spinner-border");
      const textEl = btn.querySelector(".load-more-text");
      const countEl = btn.querySelector(".load-more-count");
      if (btn.disabled) return;

      btn.disabled = true;
      if (spinner) spinner.style.display = "inline-block";

      const payload = new URLSearchParams();
      payload.append("date", date);
      payload.append("offset", String(offset));
      payload.append("limit", String(limit));
      payload.append("all_tasks", String((window.calendarPageData && window.calendarPageData.allTasks) || 0));
      payload.append("search", String((window.calendarPageData && window.calendarPageData.search) || ""));
      payload.append("start_date", String((window.calendarPageData && window.calendarPageData.startDate) || ""));
      payload.append("end_date", String((window.calendarPageData && window.calendarPageData.endDate) || ""));
      payload.append("task_date_mode", String((window.calendarPageData && window.calendarPageData.taskDateMode) || "all"));
      payload.append("sort_order", String((window.calendarPageData && window.calendarPageData.sortOrder) || "asc"));

      fetch("../ajax/calendar_load_more_cards.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: payload.toString(),
      })
        .then((r) => r.json())
        .then((json) => {
          if (!json || json.status !== "ok") {
            throw new Error("load_more_failed");
          }
          if (json.html) {
            const temp = document.createElement("div");
            temp.innerHTML = json.html;
            while (temp.firstElementChild) {
              const node = temp.firstElementChild;
              zone.insertBefore(node, container);
              if (node.classList && node.classList.contains("calendar-item-card")) {
                bindCardDragEvents(node);
              }
            }
          }
          btn.dataset.offset = String(json.next_offset || (offset + limit));
          if (json.has_more && Number(json.remaining_count) > 0) {
            if (countEl) countEl.textContent = `(${json.remaining_count})`;
          } else {
            container.style.display = "none";
          }
        })
        .catch(() => {
          alert("Unable to load more cards");
        })
        .finally(() => {
          btn.disabled = false;
          if (spinner) spinner.style.display = "none";
          if (textEl) textEl.style.display = "inline";
        });
    });
  }

  window.openTaskFromCalendar = function (e, taskId) {
    if (!e) return;
    const target = e.target;
    if (
      target.closest(".dropdown") ||
      target.closest("a") ||
      target.closest("button") ||
      target.closest("input") ||
      target.closest("textarea")
    ) {
      return;
    }
    if (typeof window.openTaskSidebar === "function") {
      window.openTaskSidebar(taskId);
    }
  };

  function focusSelectedDayColumn() {
    // On mobile/touch devices, never auto-scroll the board.
    // Keep initial position stable until user manually scrolls.
    const isTouchLike =
      ("ontouchstart" in window) ||
      (window.matchMedia && window.matchMedia("(pointer: coarse)").matches);
    const viewportWidth =
      (window.visualViewport && window.visualViewport.width) ||
      window.innerWidth ||
      document.documentElement.clientWidth ||
      0;
    if (isTouchLike || viewportWidth <= 1024) {
      return;
    }

    const selected = document.querySelector(".calendar-day-column.selected-day");
    if (!selected) return;
    // Keep horizontal movement inside board scroller only (never shift whole page under sidebar).
    if (boardWrap) {
      const targetLeft = selected.offsetLeft - Math.max(0, (boardWrap.clientWidth - selected.offsetWidth) / 2);
      boardWrap.scrollLeft = Math.max(0, targetLeft);
    }
    if (window.scrollX !== 0) {
      window.scrollTo(0, window.scrollY);
    }
  }

  function buildCalendarUrl(params) {
    const url = new URL(window.location.href);
    Object.keys(params).forEach((k) => {
      const v = params[k];
      if (v === null || v === "") {
        url.searchParams.delete(k);
      } else {
        url.searchParams.set(k, v);
      }
    });
    return url.toString();
  }

  window.toggleDateRangeCard = function () {
    const card = document.querySelector("#project-menu-calendar .date-range");
    if (!card) return;
    card.style.display = card.style.display === "none" || !card.style.display ? "block" : "none";
  };

  window.selectQuickRange = function (range) {
    const now = new Date();
    const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const start = new Date(end);

    if (range === "last7") start.setDate(end.getDate() - 6);
    if (range === "last30") start.setDate(end.getDate() - 29);
    if (range === "last90") start.setDate(end.getDate() - 89);
    if (range === "last6months") start.setMonth(end.getMonth() - 6);
    if (range === "thisYear") start.setMonth(0, 1);

    const fmt = (d) => {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    };
    window.location.href = buildCalendarUrl({
      start_date: fmt(start),
      end_date: fmt(end),
    });
  };

  window.selectCustomRange = function () {
    if (typeof event !== "undefined" && event) event.preventDefault();
    const start = document.getElementById("customStart");
    const end = document.getElementById("customEnd");
    const startVal = start ? start.value : "";
    const endVal = end ? end.value : "";
    window.location.href = buildCalendarUrl({
      start_date: startVal || null,
      end_date: endVal || null,
    });
  };

  window.clearDateRange = function () {
    if (typeof event !== "undefined" && event) event.preventDefault();
    window.location.href = buildCalendarUrl({
      start_date: null,
      end_date: null,
    });
  };

  initModal();
  initDragAndDrop();
  initCalendarLoadMore();
  focusSelectedDayColumn();

  // Sidebar shortcut: calendar.php?open_event=1 opens create-event modal automatically.
  try {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get("open_event") === "1" && openModalBtn) {
      openModalBtn.dataset.mode = "create";
      setTimeout(function () { openModalBtn.click(); }, 60);
    }
  } catch (_) {}

  // Unlock frozen board by clicking outside task/event cards.
  document.addEventListener("click", function (e) {
    if (!boardFrozen) return;
    const clickedCard = !!e.target.closest(".calendar-item-card");
    if (!clickedCard) {
      unfreezeBoardScroll();
    }
  });

  const closeEventBtn = document.getElementById("close-event-sidebar");
  if (closeEventBtn) {
    closeEventBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const sidebar = document.getElementById("event-sidebar");
      if (sidebar) sidebar.classList.remove("open");
    });
  }

  document.addEventListener("click", function (event) {
    const sidebar = document.getElementById("event-sidebar");
    if (!sidebar || !sidebar.classList.contains("open")) return;
    if (window.eventSidebarPreventCloseUntil && Date.now() < window.eventSidebarPreventCloseUntil) {
      return;
    }
    const isDropdownClick = !!event.target.closest(".dropdown-menu, .dropdown-item, .action-toggle");
    const isSidebarTrigger = !!event.target.closest('[onclick*="openEventSidebar"], [onclick*="openEventFromCalendar"], .view-event-btn, .event-item-card');
    if (!sidebar.contains(event.target) && !isDropdownClick && !isSidebarTrigger) {
      sidebar.classList.remove("open");
    }
  });

  const sidebarEditLink = document.getElementById("event-sidebar-edit-link");
  if (sidebarEditLink) {
    sidebarEditLink.addEventListener("click", function (e) {
      e.preventDefault();
      const sidebar = document.getElementById("event-sidebar");
      const eventId = sidebar && sidebar.dataset ? sidebar.dataset.eventId : "";
      if (eventId) window.editEventFromCard(eventId);
    });
  }

  const sidebarDeleteLink = document.getElementById("event-sidebar-delete-link");
  if (sidebarDeleteLink) {
    sidebarDeleteLink.addEventListener("click", function (e) {
      e.preventDefault();
      const sidebar = document.getElementById("event-sidebar");
      const eventId = sidebar && sidebar.dataset ? sidebar.dataset.eventId : "";
      if (eventId) window.deleteEventFromCard(eventId);
    });
  }
})();
