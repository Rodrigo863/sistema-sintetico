<?php
require 'config.php';

$pdo = conectarDB();
$pdo->exec("ALTER TABLE pagos ADD COLUMN IF NOT EXISTS comprobante_path VARCHAR(255) DEFAULT NULL AFTER observacion");
$hoy = date('Y-m-d');

$canchas = $pdo->query("SELECT * FROM canchas WHERE estado = 'activa' ORDER BY nombre")->fetchAll();
$reservas = $pdo->query(
    "SELECT r.id, r.cancha_id, r.fecha, r.hora_inicio, r.hora_fin, r.estado
     FROM reservas r
     INNER JOIN canchas c ON c.id = r.cancha_id
     WHERE r.fecha >= CURDATE()
       AND r.estado <> 'cancelado'
       AND c.estado = 'activa'
     ORDER BY r.fecha ASC, r.hora_inicio ASC"
)->fetchAll();

$proximaReservaId = (int)$pdo->query(
    "SELECT AUTO_INCREMENT
     FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'reservas'"
)->fetchColumn();
if ($proximaReservaId <= 0) {
    $proximaReservaId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM reservas")->fetchColumn();
}

$mensaje = trim($_GET['mensaje'] ?? '');
$error = trim($_GET['error'] ?? '');

include 'partials/header.php';
?>

<main class="container">
  <article class="panel hero">
    <h1>Reservar cancha</h1>
    <p>Eleg&iacute; un horario libre y complet&aacute; tus datos para solicitar la reserva.</p>
  </article>

  <article class="panel calendar-panel">
    <div class="court-tabs" id="courtTabs"></div>

    <div class="calendar-toolbar">
      <div>
        <h2 id="calendarTitle">Reservas</h2>
        <span class="muted">Selecciona un horario libre para crear una reserva.</span>
      </div>
      <div class="toolbar-actions">
        <button type="button" class="secondary" id="prevWeek">Anterior</button>
        <button type="button" class="secondary" id="todayWeek">Hoy</button>
        <button type="button" class="secondary" id="nextWeek">Siguiente</button>
      </div>
    </div>

    <?php if (empty($canchas)): ?>
      <p class="empty-state">No hay canchas disponibles para reserva.</p>
    <?php else: ?>
      <div class="calendar-frame">
        <div class="week-calendar-header" id="weekCalendarHeader"></div>
        <div class="calendar-scroll">
          <div class="week-calendar" id="weekCalendar"></div>
        </div>
      </div>
    <?php endif; ?>
  </article>
</main>

<div class="modal-backdrop" id="reservationModal" aria-hidden="true">
  <section class="modal">
    <header class="modal-header">
      <div class="modal-title-row">
        <h2>Adicionar reserva</h2>
        <span class="modal-title-badge" id="modalReservationNumber">#<?= (int)$proximaReservaId ?></span>
      </div>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <form action="guardar_reserva_publica.php" method="post" class="modal-body" enctype="multipart/form-data" id="publicReservationForm">
      <input type="hidden" name="cancha_id" id="modalCanchaId">
      <input type="hidden" name="fecha" id="modalFecha">
      <input type="hidden" name="hora_inicio" id="modalHoraInicio">
      <input type="hidden" name="hora_fin" id="modalHoraFin">

      <div class="modal-summary" id="modalSummary">
        <span id="modalSummaryText"></span>
        <input type="number" id="modalReservationDuration" min="1" step="1" value="1" aria-label="Horas de reserva">
        <span>hora(s)</span>
      </div>

      <div class="grid compact">
        <label>Nombre <input type="text" name="cliente_nombre" placeholder="Tu nombre" required></label>
        <label>Tel&eacute;fono <input type="text" name="cliente_telefono" placeholder="098..." required></label>
        <label class="wide">Precio total <input type="text" name="precio_total" id="modalPrecioTotal" class="money-input" inputmode="numeric" required readonly></label>
        <label>Abono <input type="text" name="monto_pago" id="modalMontoPago" class="money-input" inputmode="numeric" value="0"></label>
        <label>M&eacute;todo
          <select name="metodo" id="modalReservaMetodo">
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="otro">Otro</option>
          </select>
        </label>
        <label>Comprobante
          <input type="file" name="comprobante_pago" id="modalComprobantePago" accept="image/*">
        </label>
        <label class="wide">Detalle <textarea name="observacion" rows="2" class="compact-detail"></textarea></label>
      </div>

      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cerrar</button>
        <button type="submit">Guardar</button>
      </footer>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="confirmReservationModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Estas por reservar</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <div class="reservation-detail" id="confirmReservationSummary"></div>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Cancelar</button>
        <button type="button" id="confirmReservationSubmit">Confirmar reserva</button>
      </footer>
    </div>
  </section>
</div>

<div class="modal-backdrop<?= $mensaje || $error ? ' open' : '' ?>" id="clientMessageModal" aria-hidden="<?= $mensaje || $error ? 'false' : 'true' ?>">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2><?= $error ? 'Aviso' : 'Reserva' ?></h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <p class="modal-message" id="clientMessageText"><?= e($error ?: $mensaje) ?></p>
      <footer class="modal-footer">
        <button type="button" data-close-modal>Entendido</button>
      </footer>
    </div>
  </section>
</div>

<script>
  const courts = <?= json_encode($canchas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const reservations = <?= json_encode($reservas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const nextReservationId = <?= (int)$proximaReservaId ?>;
  const weekCalendar = document.getElementById('weekCalendar');
  const weekCalendarHeader = document.getElementById('weekCalendarHeader');
  const courtTabs = document.getElementById('courtTabs');
  const calendarTitle = document.getElementById('calendarTitle');
  const reservationModal = document.getElementById('reservationModal');
  const publicReservationForm = document.getElementById('publicReservationForm');
  const confirmReservationModal = document.getElementById('confirmReservationModal');
  const confirmReservationSummary = document.getElementById('confirmReservationSummary');
  const confirmReservationSubmit = document.getElementById('confirmReservationSubmit');
  const clientMessageModal = document.getElementById('clientMessageModal');
  const clientMessageText = document.getElementById('clientMessageText');
  const hourStart = 7;
  const hourEnd = 24;
  const dayNames = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  let selectedCourtId = courts[0] ? Number(courts[0].id) : null;
  let weekStart = new Date(today);
  let dragSelection = null;
  let reservationModalState = null;
  let publicReservationSubmitConfirmed = false;
  const mobileCalendarMedia = window.matchMedia('(max-width: 700px)');

  function visibleCalendarDays() {
    return mobileCalendarMedia.matches ? 2 : 7;
  }

  function money(value) {
    return Number(value || 0).toLocaleString('es-PY', { maximumFractionDigits: 0 });
  }

  function moneyDigits(value) {
    return String(value ?? '').replace(/\D/g, '');
  }

  function formatMoneyInput(input) {
    const digits = moneyDigits(input.value);
    input.value = digits === '' ? '' : money(Number(digits));
  }

  function prepareMoneyInputs(form) {
    form.querySelectorAll('.money-input').forEach((input) => {
      input.value = moneyDigits(input.value) || '0';
    });
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  function showClientMessage(message) {
    clientMessageText.textContent = message;
    clientMessageModal.classList.add('open');
    clientMessageModal.setAttribute('aria-hidden', 'false');
  }

  function openConfirmReservationModal() {
    if (!publicReservationForm || !confirmReservationModal || !confirmReservationSummary || !reservationModalState) {
      return;
    }

    const court = courts.find((item) => Number(item.id) === Number(reservationModalState.courtId));
    const total = Number(moneyDigits(document.getElementById('modalPrecioTotal')?.value) || 0);
    const paid = Number(moneyDigits(document.getElementById('modalMontoPago')?.value) || 0);
    const pending = Math.max(total - paid, 0);
    const endHour = reservationModalState.startHour + Number(reservationModalState.duration || 1);
    const duration = Math.max(1, Number(reservationModalState.duration || 1));
    const durationLabel = `${duration} ${duration === 1 ? 'hora' : 'horas'}`;

    confirmReservationSummary.innerHTML = `
      <dl>
        <div><dt>Cancha</dt><dd>${escapeHtml(court ? court.nombre : 'Cancha')}</dd></div>
        <div><dt>Dia</dt><dd>${escapeHtml(reservationModalDateLabel(reservationModalState.date))}</dd></div>
        <div><dt>Hora</dt><dd>${timeLabel(reservationModalState.startHour)} a ${timeLabel(endHour)} | ${durationLabel}</dd></div>
        <div><dt>Total</dt><dd>${money(total)}</dd></div>
        <div><dt>Abono</dt><dd>${money(paid)}</dd></div>
        <div><dt>Saldo pendiente</dt><dd>${money(pending)}</dd></div>
      </dl>
    `;
    confirmReservationModal.classList.add('open');
    confirmReservationModal.setAttribute('aria-hidden', 'false');
  }

  function addDays(date, days) {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  }

  function toDateKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function timeLabel(hour) {
    return String(hour).padStart(2, '0') + ':00';
  }

  function reservationHour(value) {
    return Number(String(value).slice(0, 2));
  }

  function isPastSlot(date, hour) {
    if (date !== toDateKey(new Date())) {
      return false;
    }

    const now = new Date();
    return hour <= now.getHours();
  }

  function reservationModalDateLabel(date) {
    const [year, month, day] = String(date).split('-').map(Number);
    const selectedDate = new Date(year, month - 1, day);
    const weekday = selectedDate.toLocaleDateString('es-PY', { weekday: 'long' });
    return `${weekday} ${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}`;
  }

  function renderCourtTabs() {
    if (!courtTabs) return;
    courtTabs.innerHTML = '';
    courts.forEach((court) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = court.nombre;
      button.classList.toggle('active', Number(court.id) === selectedCourtId);
      button.addEventListener('click', () => {
        selectedCourtId = Number(court.id);
        renderCourtTabs();
        renderCalendar();
      });
      courtTabs.appendChild(button);
    });
  }

  function renderCalendar() {
    if (!weekCalendar || !weekCalendarHeader || !selectedCourtId) return;
    const todayKey = toDateKey(today);
    const daysToShow = visibleCalendarDays();
    const weekDays = Array.from({ length: daysToShow }, (_, index) => addDays(weekStart, index));
    const end = addDays(weekStart, daysToShow - 1);
    calendarTitle.textContent = `${weekDays[0].toLocaleDateString('es-PY', { day: '2-digit', month: 'short' })} - ${end.toLocaleDateString('es-PY', { day: '2-digit', month: 'short', year: 'numeric' })}`;
    document.getElementById('prevWeek').disabled = toDateKey(weekStart) === todayKey;
    weekCalendar.innerHTML = '';
    weekCalendarHeader.innerHTML = '';
    weekCalendar.style.setProperty('--calendar-rows', String(hourEnd - hourStart));
    weekCalendar.style.setProperty('--calendar-days', String(daysToShow));
    weekCalendarHeader.style.setProperty('--calendar-days', String(daysToShow));

    const corner = document.createElement('div');
    corner.className = 'calendar-corner';
    weekCalendarHeader.appendChild(corner);

    weekDays.forEach((date) => {
      const header = document.createElement('div');
      header.className = 'calendar-day';
      header.textContent = `${dayNames[date.getDay()]} ${date.getDate()}/${date.getMonth() + 1}`;
      weekCalendarHeader.appendChild(header);
    });

    for (let hour = hourStart; hour < hourEnd; hour++) {
      const row = hour - hourStart + 1;
      const label = document.createElement('div');
      label.className = 'calendar-hour';
      label.textContent = timeLabel(hour);
      label.style.gridColumn = '1';
      label.style.gridRow = String(row);
      weekCalendar.appendChild(label);

      weekDays.forEach((date, index) => {
        const slot = document.createElement('button');
        slot.type = 'button';
        slot.className = 'calendar-slot';
        slot.dataset.date = toDateKey(date);
        slot.dataset.hour = hour;
        if (isPastSlot(slot.dataset.date, hour)) {
          slot.classList.add('slot-disabled');
          slot.disabled = true;
        }
        if (isSlotInSelection(slot.dataset.date, hour)) {
          slot.classList.add('slot-selected');
        }
        slot.style.gridColumn = String(index + 2);
        slot.style.gridRow = String(row);
        slot.addEventListener('pointerdown', (event) => startSlotSelection(event, slot.dataset.date, Number(slot.dataset.hour)));
        slot.addEventListener('pointerenter', () => updateSlotSelection(slot.dataset.date, Number(slot.dataset.hour)));
        weekCalendar.appendChild(slot);
      });
    }

    reservations
      .filter((item) => Number(item.cancha_id) === selectedCourtId && item.estado !== 'cancelado')
      .filter((item) => weekDays.some((date) => toDateKey(date) === item.fecha))
      .forEach((item) => {
        const dayIndex = weekDays.findIndex((date) => toDateKey(date) === item.fecha);
        const start = reservationHour(item.hora_inicio);
        const endHour = reservationHour(item.hora_fin);
        const duration = Math.max(1, endHour - start);
        const block = document.createElement('button');
        block.type = 'button';
        block.className = `reservation-block ${item.estado}`;
        block.style.gridColumn = String(dayIndex + 2);
        block.style.gridRow = `${start - hourStart + 1} / span ${duration}`;
        block.innerHTML = `<strong>${String(item.hora_inicio).slice(0, 5)} - ${String(item.hora_fin).slice(0, 5)}</strong><span>Ocupado</span>`;
        block.addEventListener('pointerdown', (event) => {
          event.preventDefault();
          event.stopPropagation();
          dragSelection = null;
          paintSlotSelection();
        });
        weekCalendar.appendChild(block);
      });
  }

  function isSlotInSelection(date, hour) {
    if (!dragSelection || dragSelection.date !== date) {
      return false;
    }
    const startHour = Math.min(dragSelection.startHour, dragSelection.endHour);
    const endHour = Math.max(dragSelection.startHour, dragSelection.endHour);
    return hour >= startHour && hour <= endHour;
  }

  function paintSlotSelection() {
    document.querySelectorAll('.calendar-slot').forEach((slot) => {
      slot.classList.toggle('slot-selected', isSlotInSelection(slot.dataset.date, Number(slot.dataset.hour)));
    });
  }

  function hasConflict(date, startHour, endHour) {
    return reservations.some((item) => {
      if (Number(item.cancha_id) !== selectedCourtId || item.fecha !== date || item.estado === 'cancelado') {
        return false;
      }
      const start = reservationHour(item.hora_inicio);
      const end = reservationHour(item.hora_fin);
      return start < endHour && end > startHour;
    });
  }

  function syncReservationDuration(nextDuration) {
    if (!reservationModalState) return;
    const duration = Math.max(1, Math.floor(Number(nextDuration || 1)));
    const endHour = reservationModalState.startHour + duration;
    if (endHour > hourEnd) {
      showClientMessage('La reserva no puede pasar de las 24:00.');
      document.getElementById('modalReservationDuration').value = reservationModalState.duration;
      return;
    }
    if (hasConflict(reservationModalState.date, reservationModalState.startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      document.getElementById('modalReservationDuration').value = reservationModalState.duration;
      return;
    }
    const court = courts.find((item) => Number(item.id) === Number(reservationModalState.courtId));
    reservationModalState.duration = duration;
    document.getElementById('modalReservationDuration').value = duration;
    document.getElementById('modalHoraFin').value = timeLabel(endHour);
    document.getElementById('modalPrecioTotal').value = money(court ? Number(court.precio_hora || 0) * duration : 0);
    document.getElementById('modalReservationNumber').textContent = `#${nextReservationId}`;
    document.getElementById('modalSummaryText').textContent = `${court ? court.nombre : 'Cancha'} | ${reservationModalDateLabel(reservationModalState.date)} | ${timeLabel(reservationModalState.startHour)} a ${timeLabel(endHour)} |`;
  }

  function startSlotSelection(event, date, hour) {
    event.preventDefault();
    if (isPastSlot(date, hour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      return;
    }
    dragSelection = { date, startHour: hour, endHour: hour };
    paintSlotSelection();
  }

  function updateSlotSelection(date, hour) {
    if (!dragSelection || dragSelection.date !== date) {
      return;
    }
    dragSelection.endHour = hour;
    paintSlotSelection();
  }

  function finishSlotSelection() {
    if (!dragSelection) {
      return;
    }
    const date = dragSelection.date;
    const startHour = Math.min(dragSelection.startHour, dragSelection.endHour);
    const endHour = Math.max(dragSelection.startHour, dragSelection.endHour) + 1;
    dragSelection = null;
    if (isPastSlot(date, startHour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      paintSlotSelection();
      return;
    }
    if (hasConflict(date, startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      paintSlotSelection();
      return;
    }
    paintSlotSelection();
    setTimeout(() => openReservationModal(date, startHour, endHour), 120);
  }

  function openReservationModal(date, startHour, endHour) {
    if (hasConflict(date, startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      return;
    }
    document.getElementById('modalCanchaId').value = selectedCourtId;
    document.getElementById('modalFecha').value = date;
    document.getElementById('modalHoraInicio').value = timeLabel(startHour);
    document.getElementById('modalHoraFin').value = timeLabel(endHour);
    const duration = Math.max(1, endHour - startHour);
    reservationModalState = { courtId: selectedCourtId, date, startHour, duration };
    syncReservationDuration(duration);
    document.getElementById('modalMontoPago').value = '0';
    document.getElementById('modalReservaMetodo').value = 'efectivo';
    document.getElementById('modalComprobantePago').value = '';
    reservationModal.classList.add('open');
    reservationModal.setAttribute('aria-hidden', 'false');
  }

  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = button.closest('.modal-backdrop');
      if (!modal) return;
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    });
  });

  document.querySelectorAll('.money-input').forEach((input) => {
    formatMoneyInput(input);
    input.addEventListener('input', () => formatMoneyInput(input));
    input.addEventListener('blur', () => {
      if (input.value.trim() === '') {
        input.value = '0';
      }
    });
  });

  document.getElementById('modalMontoPago')?.addEventListener('input', (event) => {
    const amount = Number(moneyDigits(event.currentTarget.value) || 0);
    event.currentTarget.setCustomValidity(amount > 0 && amount < 20000 ? 'El abono minimo es 20.000.' : '');
  });

  document.getElementById('modalComprobantePago')?.addEventListener('change', (event) => {
    if (event.currentTarget.files.length > 0) {
      document.getElementById('modalReservaMetodo').value = 'transferencia';
    }
  });

  document.getElementById('modalReservationDuration')?.addEventListener('change', (event) => {
    syncReservationDuration(event.currentTarget.value);
  });

  publicReservationForm?.addEventListener('submit', (event) => {
    if (publicReservationSubmitConfirmed) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    if (!publicReservationForm.reportValidity()) {
      return;
    }

    const amountInput = document.getElementById('modalMontoPago');
    const amount = Number(moneyDigits(amountInput?.value) || 0);
    if (amountInput && amount > 0 && amount < 20000) {
      amountInput.setCustomValidity('El abono minimo es 20.000.');
      amountInput.reportValidity();
      amountInput.setCustomValidity('');
      return;
    }

    openConfirmReservationModal();
  });

  confirmReservationSubmit?.addEventListener('click', () => {
    if (!publicReservationForm) {
      return;
    }
    publicReservationSubmitConfirmed = true;
    confirmReservationSubmit.disabled = true;
    confirmReservationSubmit.textContent = 'Guardando...';
    publicReservationForm.requestSubmit();
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!event.defaultPrevented) {
        prepareMoneyInputs(form);
      }
    });
  });

  document.getElementById('prevWeek')?.addEventListener('click', () => {
    const previous = addDays(weekStart, -visibleCalendarDays());
    weekStart = previous < today ? new Date(today) : previous;
    renderCalendar();
  });
  document.getElementById('nextWeek')?.addEventListener('click', () => {
    weekStart = addDays(weekStart, visibleCalendarDays());
    renderCalendar();
  });
  document.getElementById('todayWeek')?.addEventListener('click', () => {
    weekStart = new Date(today);
    renderCalendar();
  });
  document.addEventListener('pointerup', finishSlotSelection);
  document.addEventListener('mouseup', finishSlotSelection);
  document.addEventListener('pointercancel', () => {
    dragSelection = null;
    paintSlotSelection();
  });
  if (mobileCalendarMedia.addEventListener) {
    mobileCalendarMedia.addEventListener('change', renderCalendar);
  } else if (mobileCalendarMedia.addListener) {
    mobileCalendarMedia.addListener(renderCalendar);
  }

  renderCourtTabs();
  renderCalendar();
</script>

<?php include 'partials/footer.php'; ?>
