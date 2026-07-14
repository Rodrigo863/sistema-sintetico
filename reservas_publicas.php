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
$reservaCreadaId = (int)($_GET['reserva_id'] ?? 0);

include 'partials/header.php';
?>

<main class="container">
  <article class="panel calendar-panel public-calendar-panel">
    <div class="public-courts-header">
      <div class="court-tabs" id="courtTabs"></div>
      <button type="button" class="secondary public-cancel-reservation" id="openPublicCancellation">Cancelar mi reserva</button>
    </div>

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

<a
  class="public-whatsapp-button"
  href="https://wa.me/595982830166?text=Hola%2C%20quiero%20realizar%20una%20reserva."
  target="_blank"
  rel="noopener noreferrer"
  aria-label="Reservar por WhatsApp al 0982 830 166"
  title="Reservar por WhatsApp"
>
  <svg viewBox="0 0 24 24" aria-hidden="true">
    <path d="M12 2a9.7 9.7 0 0 0-8.36 14.62L2.2 21.8l5.3-1.39A9.8 9.8 0 1 0 12 2Zm0 17.7a7.7 7.7 0 0 1-3.92-1.07l-.28-.17-3.14.82.84-3.06-.18-.3A7.7 7.7 0 1 1 12 19.7Zm4.23-5.77c-.23-.12-1.37-.68-1.58-.75-.21-.08-.37-.12-.52.11-.15.24-.6.75-.73.9-.14.16-.27.18-.5.06-.23-.11-.98-.36-1.86-1.15a6.95 6.95 0 0 1-1.29-1.6c-.13-.23-.01-.36.1-.47.1-.1.23-.27.35-.4.11-.14.15-.24.23-.4.08-.15.04-.29-.02-.4-.06-.12-.52-1.26-.71-1.72-.19-.45-.38-.39-.52-.4h-.45c-.15 0-.4.06-.61.29-.21.23-.81.79-.81 1.93 0 1.13.83 2.23.94 2.39.12.15 1.63 2.48 3.94 3.48.55.24.98.38 1.31.49.55.17 1.05.15 1.45.09.44-.07 1.37-.56 1.56-1.1.19-.55.19-1.02.13-1.11-.06-.1-.21-.16-.44-.28Z"/>
  </svg>
  <span>Reservar por WhatsApp</span>
</a>

<div class="modal-backdrop" id="publicCancellationModal" aria-hidden="true">
  <section class="modal small-modal">
    <header class="modal-header">
      <h2>Cancelar mi reserva</h2>
      <button type="button" class="icon-button" data-close-modal>&times;</button>
    </header>
    <div class="modal-body">
      <p class="modal-message">Se te devolver&aacute; el 20% de tu abono. Escr&iacute;benos por WhatsApp para solicitar la cancelaci&oacute;n.</p>
      <footer class="modal-footer">
        <button type="button" class="secondary" data-close-modal>Volver</button>
        <a
          class="btn public-cancellation-whatsapp"
          href="https://wa.me/595982830166?text=Hola%2C%20quiero%20cancelar%20mi%20reserva.%20Estos%20son%20mis%20datos%3A%20"
          target="_blank"
          rel="noopener noreferrer"
        >Escribir por WhatsApp</a>
      </footer>
    </div>
  </section>
</div>

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
        <label>Inicio
          <select id="modalHoraInicioVisible" aria-label="Hora de inicio"></select>
        </label>
        <label>Fin
          <select id="modalHoraFinVisible" aria-label="Hora de fin"></select>
        </label>
        <span class="duration-display" id="modalReservationDuration">1 hora</span>
      </div>

      <div class="grid compact">
        <label>Nombre <input type="text" name="cliente_nombre" placeholder="Tu nombre" required></label>
        <label>Tel&eacute;fono <input type="text" name="cliente_telefono" placeholder="098..." inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required></label>
        <label class="wide">Precio total <input type="text" name="precio_total" id="modalPrecioTotal" class="money-input" inputmode="numeric" required readonly></label>
        <label>Abono <input type="text" name="monto_pago" id="modalMontoPago" class="money-input" inputmode="numeric" value="0"></label>
        <label>M&eacute;todo
          <select name="metodo" id="modalReservaMetodo">
            <option value="transferencia" selected>Transferencia</option>
          </select>
        </label>
        <label>Comprobante
          <input type="file" name="comprobante_pago" id="modalComprobantePago" accept="image/*">
        </label>
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
  document.body.classList.add('public-reservas-view');

  const courts = <?= json_encode($canchas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const reservations = <?= json_encode($reservas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const nextReservationId = <?= (int)$proximaReservaId ?>;
  const createdReservationId = <?= (int)$reservaCreadaId ?>;
  const autoHideSuccessMessage = <?= $mensaje !== '' && $error === '' ? 'true' : 'false' ?>;
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
  const hourStart = 13;
  const hourEnd = 24;
  const slotStep = 0.5;
  const minReservationDuration = 1;
  const dayNames = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  let selectedCourtId = courts[0] ? Number(courts[0].id) : null;
  let weekStart = new Date(today);
  let dragSelection = null;
  let reservationModalState = null;
  let pendingPublicReservation = null;
  let publicCalendarHelpVisible = true;
  let publicCalendarHelpTimer = null;
  let publicReservationSubmitConfirmed = false;
  const mobileCalendarMedia = window.matchMedia('(max-width: 700px)');

  function shouldProtectSubmit(form) {
    const action = form.getAttribute('action') || '';
    return action.includes('guardar_reserva_publica.php');
  }

  function lockSubmitForm(form, submitter = null) {
    if (!shouldProtectSubmit(form)) {
      return;
    }

    form.dataset.submitting = '1';
    const buttons = new Set(Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]')));
    if (form.id) {
      document.querySelectorAll(`button[form="${form.id}"], input[form="${form.id}"]`).forEach((button) => buttons.add(button));
    }
    if (submitter) {
      buttons.add(submitter);
    }

    buttons.forEach((button) => {
      button.disabled = true;
      if (button.tagName === 'BUTTON') {
        button.dataset.originalText = button.dataset.originalText || button.textContent;
        button.textContent = 'Procesando...';
      }
    });
  }

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !shouldProtectSubmit(form)) {
      return;
    }

    if (form.dataset.submitting === '1') {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

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

  function clearTransientUrlParams() {
    const url = new URL(window.location.href);
    url.searchParams.delete('mensaje');
    url.searchParams.delete('error');
    url.searchParams.delete('reserva_id');
    const cleanSearch = url.searchParams.toString();
    history.replaceState(null, '', `${url.pathname}${cleanSearch ? `?${cleanSearch}` : ''}${url.hash}`);
  }

  function autoHideClientMessage() {
    if (!autoHideSuccessMessage || !clientMessageModal.classList.contains('open')) {
      return;
    }

    setTimeout(() => {
      clientMessageModal.classList.remove('open');
      clientMessageModal.setAttribute('aria-hidden', 'true');
    }, 3500);
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
    const duration = Math.max(minReservationDuration, Number(reservationModalState.duration || 1));
    const durationLabel = `${duration.toLocaleString('es-PY', { maximumFractionDigits: 1 })} ${duration === 1 ? 'hora' : 'horas'}`;

    confirmReservationSummary.innerHTML = `
      <dl>
        <div><dt>Cancha</dt><dd>${escapeHtml(court ? court.nombre : 'Cancha')}</dd></div>
        <div><dt>Dia</dt><dd>${escapeHtml(reservationModalDateLabel(reservationModalState.date))}</dd></div>
        <div><dt>Hora</dt><dd>${displayTimeLabel(reservationModalState.startHour)} a ${displayTimeLabel(endHour)} | ${durationLabel}</dd></div>
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
    const totalMinutes = Math.round(Number(hour || 0) * 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
  }

  function displayTimeLabel(hour) {
    const label = timeLabel(hour);
    return label === '24:00' ? '00:00' : label;
  }

  function displayReservationTime(value) {
    const label = String(value || '').slice(0, 5);
    return label === '24:00' ? '00:00' : label;
  }

  function durationLabel(duration) {
    const totalMinutes = Math.round(Number(duration || 0) * 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`;
  }

  function reservationHour(value) {
    return reservationTimeToHours(value) ?? Number(String(value).slice(0, 2));
  }

  function reservationTimeToHours(value) {
    const [hours, minutes] = String(value || '').split(':').map(Number);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
      return null;
    }
    return hours + (minutes / 60);
  }

  function normalizeSlotHour(value) {
    if (value === null) {
      return null;
    }
    return Math.round(Number(value) / slotStep) * slotStep;
  }

  function fillTimeSelect(select, startHour, endHour, selectedHour, isAllowed = null) {
    if (!select) {
      return;
    }

    select.innerHTML = '';
    for (let hour = startHour; hour <= endHour; hour += slotStep) {
      if (isAllowed && !isAllowed(hour)) {
        continue;
      }
      const option = document.createElement('option');
      option.value = timeLabel(hour);
      option.textContent = displayTimeLabel(hour);
      select.appendChild(option);
    }
    if (select.options.length === 0) {
      return;
    }

    const preferredValue = timeLabel(Math.min(endHour, Math.max(startHour, selectedHour)));
    select.value = Array.from(select.options).some((option) => option.value === preferredValue)
      ? preferredValue
      : select.options[0].value;
  }

  function populateReservationTimeSelects(startHour = hourStart, endHour = startHour + minReservationDuration) {
    const startSelect = document.getElementById('modalHoraInicioVisible');
    const endSelect = document.getElementById('modalHoraFinVisible');
    if (!startSelect || !endSelect) {
      return;
    }

    const selectedStart = Math.min(hourEnd - minReservationDuration, Math.max(hourStart, startHour));
    const selectedEnd = Math.min(hourEnd, Math.max(selectedStart + minReservationDuration, endHour));
    fillTimeSelect(
      startSelect,
      hourStart,
      hourEnd - minReservationDuration,
      selectedStart,
      (hour) => !isPastSlot(reservationModalState?.date || toDateKey(today), hour)
        && !hasConflict(reservationModalState?.date || toDateKey(today), hour, hour + minReservationDuration)
    );
    const currentStart = reservationTimeToHours(startSelect.value) ?? selectedStart;
    fillTimeSelect(
      endSelect,
      currentStart + minReservationDuration,
      hourEnd,
      selectedEnd,
      (hour) => !hasConflict(reservationModalState?.date || toDateKey(today), currentStart, hour)
    );
    if (startSelect.options.length === 0 || endSelect.options.length === 0) {
      showClientMessage('No hay horarios disponibles para completar al menos 1 hora.');
    }
  }

  function isPastSlot(date, hour) {
    if (date !== toDateKey(new Date())) {
      return false;
    }

    const now = new Date();
    return hour < (now.getHours() + (now.getMinutes() / 60));
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
    const dateBadge = document.createElement('span');
    dateBadge.className = 'court-tabs-date';
    dateBadge.id = 'calendarDateInTabs';
    courtTabs.appendChild(dateBadge);
  }

  function renderCalendar() {
    if (!weekCalendar || !weekCalendarHeader || !selectedCourtId) return;
    const todayKey = toDateKey(today);
    const daysToShow = visibleCalendarDays();
    const weekDays = Array.from({ length: daysToShow }, (_, index) => addDays(weekStart, index));
    const end = addDays(weekStart, daysToShow - 1);
    const calendarDateLabel = `${weekDays[0].toLocaleDateString('es-PY', { day: '2-digit', month: 'short' })} - ${end.toLocaleDateString('es-PY', { day: '2-digit', month: 'short', year: 'numeric' })}`;
    calendarTitle.textContent = calendarDateLabel;
    const calendarDateInTabs = document.getElementById('calendarDateInTabs');
    if (calendarDateInTabs) {
      calendarDateInTabs.textContent = calendarDateLabel;
    }
    document.getElementById('prevWeek').disabled = toDateKey(weekStart) === todayKey;
    weekCalendar.innerHTML = '';
    weekCalendarHeader.innerHTML = '';
    const calendarRows = Math.round((hourEnd - hourStart) / slotStep);
    weekCalendar.style.setProperty('--calendar-rows', String(calendarRows));
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

    let helpSlot = null;
    for (let slotIndex = 0; slotIndex < calendarRows; slotIndex++) {
      const hour = hourStart + (slotIndex * slotStep);
      const row = slotIndex + 1;
      if (Number.isInteger(hour)) {
        const label = document.createElement('div');
        label.className = 'calendar-hour';
        label.textContent = timeLabel(hour);
        label.style.gridColumn = '1';
        label.style.gridRow = `${row} / span ${Math.round(1 / slotStep)}`;
        weekCalendar.appendChild(label);
      }

      if (hour > hourEnd - minReservationDuration) {
        continue;
      }

      weekDays.forEach((date, index) => {
        const slot = document.createElement('button');
        slot.type = 'button';
        slot.className = 'calendar-slot';
        slot.dataset.date = toDateKey(date);
        slot.dataset.hour = String(hour);
        const slotBlocked = hasConflict(slot.dataset.date, hour, hour + minReservationDuration);
        if (hour > hourEnd - minReservationDuration || isPastSlot(slot.dataset.date, hour) || slotBlocked) {
          slot.classList.add('slot-disabled');
          slot.classList.toggle('slot-occupied', slotBlocked);
          slot.disabled = true;
        } else if (publicCalendarHelpVisible && !helpSlot) {
          helpSlot = { column: index + 2, row, hour, date: slot.dataset.date };
        }
        if (
          publicCalendarHelpVisible
          && helpSlot
          && slot.dataset.date === helpSlot.date
          && hour >= helpSlot.hour
          && hour < helpSlot.hour + minReservationDuration
        ) {
          slot.classList.add('slot-help-target');
        }
        if (isSlotInSelection(slot.dataset.date, hour)) {
          slot.classList.add('slot-selected');
        }
        slot.style.gridColumn = String(index + 2);
        slot.style.gridRow = hour === hourEnd - minReservationDuration
          ? `${row} / span ${Math.round(minReservationDuration / slotStep)}`
          : String(row);
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
        if (endHour <= hourStart || start >= hourEnd) {
          return;
        }
        const visibleStart = Math.max(start, hourStart);
        const visibleEnd = Math.min(endHour, hourEnd);
        const duration = Math.max(slotStep, visibleEnd - visibleStart);
        const rowStart = Math.round((visibleStart - hourStart) / slotStep) + 1;
        const rowSpan = Math.max(1, Math.round(duration / slotStep));
        const block = document.createElement('button');
        block.type = 'button';
        const isCreatedReservation = Number(item.id) === Number(createdReservationId);
        block.className = `reservation-block ${item.estado}`;
        block.classList.toggle('just-created', isCreatedReservation);
        block.style.gridColumn = String(dayIndex + 2);
        block.style.gridRow = `${rowStart} / span ${rowSpan}`;
        block.innerHTML = isCreatedReservation
          ? `<strong>#${item.id} ${displayReservationTime(item.hora_inicio)} - ${displayReservationTime(item.hora_fin)}</strong><span>Tu reserva</span>`
          : `<strong>${displayReservationTime(item.hora_inicio)} - ${displayReservationTime(item.hora_fin)}</strong><span>Ocupado</span>`;
        block.addEventListener('pointerdown', (event) => {
          event.preventDefault();
          event.stopPropagation();
          dragSelection = null;
          paintSlotSelection();
        });
        weekCalendar.appendChild(block);
      });

    if (pendingPublicReservation && Number(pendingPublicReservation.courtId) === selectedCourtId) {
      renderPendingPublicReservation(weekDays);
    }

    if (publicCalendarHelpVisible && helpSlot) {
      renderPublicCalendarHelp(helpSlot);
      schedulePublicCalendarHelpHide();
    }
  }

  function renderPublicCalendarHelp(helpSlot) {
    const help = document.createElement('div');
    help.className = 'public-reservation-help calendar-help-overlay';
    help.style.gridColumn = String(helpSlot.column);
    help.style.gridRow = `${helpSlot.row} / span ${Math.round(minReservationDuration / slotStep)}`;
    help.innerHTML = `
      <span class="tap-help-icon" aria-hidden="true">
        <svg class="tap-help-svg" viewBox="0 0 48 48" focusable="false">
          <circle class="tap-help-svg-ring" cx="24" cy="14" r="9"></circle>
          <path class="tap-help-svg-pointer" d="M17 7v28l7-7 5 12 7-3-5-11h10L17 7z"></path>
        </svg>
      </span>
      <strong>Hac&eacute; click en una casilla libre para reservar.</strong>
    `;
    weekCalendar.appendChild(help);
  }

  function schedulePublicCalendarHelpHide() {
    if (publicCalendarHelpTimer) {
      return;
    }
    publicCalendarHelpTimer = setTimeout(() => {
      publicCalendarHelpVisible = false;
      publicCalendarHelpTimer = null;
      clearPublicCalendarHelp();
    }, 5500);
  }

  function clearPublicCalendarHelp() {
    document.querySelector('.calendar-help-overlay')?.remove();
    document.querySelectorAll('.slot-help-target').forEach((slot) => {
      slot.classList.remove('slot-help-target');
    });
  }

  function hidePublicCalendarHelp() {
    if (!publicCalendarHelpVisible) {
      return;
    }
    publicCalendarHelpVisible = false;
    if (publicCalendarHelpTimer) {
      clearTimeout(publicCalendarHelpTimer);
      publicCalendarHelpTimer = null;
    }
    clearPublicCalendarHelp();
  }

  function renderPendingPublicReservation(weekDays) {
    const dayIndex = weekDays.findIndex((date) => toDateKey(date) === pendingPublicReservation.date);
    if (dayIndex < 0) {
      return;
    }

    const visibleStart = Math.max(pendingPublicReservation.startHour, hourStart);
    const visibleEnd = Math.min(pendingPublicReservation.endHour, hourEnd);
    if (visibleEnd <= hourStart || visibleStart >= hourEnd) {
      return;
    }

    const duration = Math.max(slotStep, visibleEnd - visibleStart);
    const rowStart = Math.round((visibleStart - hourStart) / slotStep) + 1;
    const rowSpan = Math.max(1, Math.round(duration / slotStep));
    const block = document.createElement('div');
    block.className = 'reservation-block reservation-loading';
    block.style.gridColumn = String(dayIndex + 2);
    block.style.gridRow = `${rowStart} / span ${rowSpan}`;
    block.innerHTML = `<strong>${timeLabel(pendingPublicReservation.startHour)} - ${timeLabel(pendingPublicReservation.endHour)}</strong><span>Cargando...</span>`;
    weekCalendar.appendChild(block);
  }

  function showPublicReservationLoading() {
    if (!reservationModalState) {
      return;
    }

    pendingPublicReservation = {
      courtId: reservationModalState.courtId,
      date: reservationModalState.date,
      startHour: reservationModalState.startHour,
      endHour: reservationModalState.startHour + Number(reservationModalState.duration || minReservationDuration)
    };
    reservationModal.classList.remove('open');
    reservationModal.setAttribute('aria-hidden', 'true');
    confirmReservationModal.classList.remove('open');
    confirmReservationModal.setAttribute('aria-hidden', 'true');
    selectedCourtId = Number(pendingPublicReservation.courtId);
    renderCourtTabs();
    renderCalendar();
  }

  function isSlotInSelection(date, hour) {
    const range = getDragSelectionRange(date, dragSelection?.endHour);
    if (!range) {
      return false;
    }
    return hour >= range.startHour && hour < range.endHour;
  }

  function getDragSelectionRange(date, endSlotHour) {
    if (!dragSelection || dragSelection.date !== date) {
      return null;
    }

    const startHour = Math.min(dragSelection.startHour, endSlotHour);
    const endHour = Math.max(dragSelection.startHour + minReservationDuration, endSlotHour + slotStep);
    return { startHour, endHour };
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
    const duration = Math.max(minReservationDuration, Math.round(Number(nextDuration || 1) / slotStep) * slotStep);
    const endHour = reservationModalState.startHour + duration;
    if (endHour > hourEnd) {
      showClientMessage('La reserva no puede pasar de las 24:00.');
      document.getElementById('modalReservationDuration').textContent = durationLabel(reservationModalState.duration);
      return;
    }
    if (hasConflict(reservationModalState.date, reservationModalState.startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      document.getElementById('modalReservationDuration').textContent = durationLabel(reservationModalState.duration);
      return;
    }
    const court = courts.find((item) => Number(item.id) === Number(reservationModalState.courtId));
    reservationModalState.duration = duration;
    document.getElementById('modalReservationDuration').textContent = durationLabel(duration);
    document.getElementById('modalHoraFin').value = timeLabel(endHour);
    populateReservationTimeSelects(reservationModalState.startHour, endHour);
    document.getElementById('modalPrecioTotal').value = money(court ? Number(court.precio_hora || 0) * duration : 0);
    document.getElementById('modalReservationNumber').textContent = `#${nextReservationId}`;
    document.getElementById('modalSummaryText').textContent = `${court ? court.nombre : 'Cancha'} | ${reservationModalDateLabel(reservationModalState.date)} |`;
  }

  function syncReservationTimesFromInputs() {
    if (!reservationModalState) return;
    const startInput = document.getElementById('modalHoraInicioVisible');
    const endInput = document.getElementById('modalHoraFinVisible');
    const previousStart = reservationModalState.startHour;
    const previousDuration = reservationModalState.duration;
    let startHour = normalizeSlotHour(reservationTimeToHours(startInput?.value));
    let endHour = normalizeSlotHour(reservationTimeToHours(endInput?.value));

    if (startHour === null) startHour = previousStart;
    if (endHour === null) endHour = previousStart + previousDuration;

    startHour = Math.min(hourEnd - minReservationDuration, Math.max(hourStart, startHour));
    endHour = Math.min(hourEnd, Math.max(startHour + minReservationDuration, endHour));

    if (isPastSlot(reservationModalState.date, startHour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      populateReservationTimeSelects(previousStart, previousStart + previousDuration);
      return;
    }

    if (hasConflict(reservationModalState.date, startHour, endHour)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      populateReservationTimeSelects(previousStart, previousStart + previousDuration);
      return;
    }

    reservationModalState.startHour = startHour;
    reservationModalState.duration = endHour - startHour;
    document.getElementById('modalHoraInicio').value = timeLabel(startHour);
    syncReservationDuration(reservationModalState.duration);
  }

  function startSlotSelection(event, date, hour) {
    hidePublicCalendarHelp();
    event.preventDefault();
    if (hour > hourEnd - minReservationDuration) {
      showClientMessage('La duracion minima de alquiler es 1 hora.');
      return;
    }
    if (isPastSlot(date, hour)) {
      showClientMessage('No se pueden crear reservas en horarios anteriores a la hora actual.');
      return;
    }
    if (hasConflict(date, hour, hour + minReservationDuration)) {
      showClientMessage('El rango seleccionado se cruza con una reserva existente.');
      return;
    }
    dragSelection = { date, startHour: hour, endHour: hour };
    paintSlotSelection();
  }

  function updateSlotSelection(date, hour) {
    if (!dragSelection || dragSelection.date !== date) {
      return;
    }
    const range = getDragSelectionRange(date, hour);
    if (!range || hasConflict(date, range.startHour, range.endHour) || isPastSlot(date, range.startHour)) {
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
    const endHour = Math.max(dragSelection.startHour + minReservationDuration, dragSelection.endHour + slotStep);
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
    document.getElementById('modalHoraInicioVisible').value = timeLabel(startHour);
    document.getElementById('modalHoraFinVisible').value = timeLabel(endHour);
    const duration = Math.max(minReservationDuration, endHour - startHour);
    reservationModalState = { courtId: selectedCourtId, date, startHour, duration };
    syncReservationDuration(duration);
    document.getElementById('modalMontoPago').value = '0';
    document.getElementById('modalReservaMetodo').value = 'transferencia';
    document.getElementById('modalComprobantePago').value = '';
    syncPublicReceiptRequirement();
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

  document.getElementById('openPublicCancellation')?.addEventListener('click', () => {
    const modal = document.getElementById('publicCancellationModal');
    modal?.classList.add('open');
    modal?.setAttribute('aria-hidden', 'false');
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
    syncPublicReceiptRequirement();
  });

  function syncPublicReceiptRequirement() {
    const methodSelect = document.getElementById('modalReservaMetodo');
    const receiptInput = document.getElementById('modalComprobantePago');
    if (!methodSelect || !receiptInput) {
      return;
    }

    const amount = Number(moneyDigits(document.getElementById('modalMontoPago')?.value) || 0);
    const requiresReceipt = methodSelect.value === 'transferencia' && amount > 0;
    receiptInput.required = requiresReceipt;
    receiptInput.setCustomValidity(requiresReceipt && receiptInput.files.length === 0
      ? 'Adjunta el comprobante para pagar por transferencia.'
      : '');
  }

  document.getElementById('modalReservaMetodo')?.addEventListener('change', syncPublicReceiptRequirement);

  document.getElementById('modalComprobantePago')?.addEventListener('change', (event) => {
    if (event.currentTarget.files.length > 0) {
      document.getElementById('modalReservaMetodo').value = 'transferencia';
    }
    syncPublicReceiptRequirement();
  });

  document.getElementById('modalHoraInicioVisible')?.addEventListener('change', syncReservationTimesFromInputs);
  document.getElementById('modalHoraFinVisible')?.addEventListener('change', syncReservationTimesFromInputs);

  publicReservationForm?.addEventListener('submit', (event) => {
    if (publicReservationSubmitConfirmed) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    syncPublicReceiptRequirement();
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
    showPublicReservationLoading();
    publicReservationForm.requestSubmit();
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!event.defaultPrevented) {
        prepareMoneyInputs(form);
      }
    });
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || !shouldProtectSubmit(form)) {
      return;
    }

    lockSubmitForm(form, event.submitter);
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

  populateReservationTimeSelects();
  const createdReservation = reservations.find((item) => Number(item.id) === Number(createdReservationId));
  if (createdReservation) {
    selectedCourtId = Number(createdReservation.cancha_id);
  }
  renderCourtTabs();
  renderCalendar();
  autoHideClientMessage();
  clearTransientUrlParams();
</script>

<?php include 'partials/footer.php'; ?>
