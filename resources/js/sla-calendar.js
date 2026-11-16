const editor = document.querySelector('[data-sla-calendar]');

if (editor) {
    const read = (name) => editor.querySelector('[data-' + name + ']');
    const parse = (field) => {
        try { return Object.assign(Object.create(null), JSON.parse(field.value)); }
        catch { return Object.create(null); }
    };
    const weeklyField = read('weekly');
    const datesField = read('dates');
    const notesField = read('notes');
    const week = parse(weeklyField);
    const dates = parse(datesField);
    const notes = parse(notesField);
    const holidays = parse(read('holidays'));
    const date = read('date');
    const month = read('month');
    const type = read('type');
    const hours = read('hours');
    const note = read('note');
    const status = read('status');
    const calendar = read('days');
    const weekly = read('weekly-grid');
    const weekdays = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];

    function sync() {
        weeklyField.value = JSON.stringify(week);
        datesField.value = JSON.stringify(dates);
        notesField.value = JSON.stringify(notes);
    }

    function renderWindows(container, values, onChange) {
        container.replaceChildren();
        const windows = values.map((window) => [...window]);
        windows.forEach(([start, end], index) => {
            const row = document.createElement('div');
            row.className = 'grid gap-2 sm:grid-cols-3 sm:items-end';
            [start, end].forEach((value, part) => {
                const label = document.createElement('label');
                label.append((part ? 'Fim' : 'Início') + ' do intervalo ' + (index + 1));
                const input = document.createElement('input');
                input.type = 'text';
                input.inputMode = 'numeric';
                input.placeholder = 'HH:MM';
                input.value = value;
                input.maxLength = 5;
                input.addEventListener('input', () => {
                    windows[index][part] = input.value;
                    onChange(windows);
                });
                label.append(input);
                row.append(label);
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'secondary';
            remove.textContent = 'Remover intervalo ' + (index + 1);
            remove.addEventListener('click', () => {
                windows.splice(index, 1);
                onChange(windows);
                renderWindows(container, windows, onChange);
                container.lastElementChild.focus();
            });
            row.append(remove);
            container.append(row);
        });
        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'secondary';
        add.textContent = 'Adicionar intervalo';
        add.addEventListener('click', () => {
            windows.push(['08:00', '17:00']);
            onChange(windows);
            renderWindows(container, windows, onChange);
            container.children[windows.length - 1].querySelector('input').focus();
        });
        container.append(add);
    }

    weekdays.forEach((day, index) => {
        const dayNumber = index + 1;
        const item = document.createElement('details');
        const summary = document.createElement('summary');
        const container = document.createElement('div');
        container.className = 'space-y-2 py-2';
        const update = (windows) => {
            week[dayNumber] = windows;
            summary.textContent = day + ' · ' + (windows.length ? windows.map((w) => w.join('–')).join(', ') : 'sem atendimento');
            sync();
        };
        update(week[dayNumber] ?? []);
        renderWindows(container, week[dayNumber], update);
        item.append(summary, container);
        weekly.append(item);
    });

    let selectedWindows = [];
    let dirty = false;
    function loadDay(day) {
        if (dirty && !window.confirm('Descartar a alteração ainda não adicionada ao calendário?')) {
            date.value = date.dataset.selected;
            return false;
        }
        dirty = false;
        date.value = day;
        date.dataset.selected = day;
        type.value = Object.hasOwn(dates, day) ? (dates[day].length ? 'special' : 'closed') : 'normal';
        note.value = notes[day] ?? holidays[day] ?? '';
        selectedWindows = dates[day]?.length ? dates[day] : [['08:00', '17:00']];
        hours.hidden = type.value !== 'special';
        renderWindows(hours, selectedWindows, (windows) => { selectedWindows = windows; dirty = true; });
        return true;
    }

    function renderMonth() {
        const [year, number] = month.value.split('-').map(Number);
        if (!year || !number) return;
        calendar.replaceChildren();
        const first = new Date(Date.UTC(year, number - 1, 1));
        const total = new Date(Date.UTC(year, number, 0)).getUTCDate();
        weekdays.forEach((day) => {
            const heading = document.createElement('span');
            heading.className = 'text-center text-xs';
            heading.textContent = day.slice(0, 3);
            calendar.append(heading);
        });
        for (let i = 0; i < (first.getUTCDay() + 6) % 7; i++) {
            calendar.append(document.createElement('span'));
        }
        for (let i = 1; i <= total; i++) {
            const key = month.value + '-' + String(i).padStart(2, '0');
            const button = document.createElement('button');
            const saved = Object.hasOwn(dates, key);
            button.type = 'button';
            button.dataset.day = key;
            button.className = (key === date.value ? 'primary' : 'secondary') + ' min-w-0 !px-0 text-sm';
            button.textContent = i + (saved ? ' •' : holidays[key] ? ' ◦' : '');
            button.setAttribute('aria-label', key.split('-').reverse().join('/') + (saved ? ' · exceção' : '') + (holidays[key] ? ' · ' + holidays[key] : ''));
            button.setAttribute('aria-pressed', String(key === date.value));
            button.addEventListener('click', () => {
                if (!loadDay(key)) return;
                renderMonth();
                calendar.querySelector('[data-day="' + key + '"]').focus();
            });
            calendar.append(button);
        }
    }

    loadDay(date.value);
    sync();
    renderMonth();
    month.addEventListener('change', renderMonth);
    date.addEventListener('change', () => {
        if (!date.value || !loadDay(date.value)) return;
        month.value = date.value.slice(0, 7);
        renderMonth();
    });
    note.addEventListener('input', () => { dirty = true; });
    type.addEventListener('change', () => { hours.hidden = type.value !== 'special'; dirty = true; });
    read('save').addEventListener('click', () => {
        if (!date.reportValidity()) return;
        if (type.value === 'special' && !selectedWindows.length) {
            status.textContent = 'Adicione pelo menos um intervalo para o horário especial.';
            return;
        }
        if (type.value === 'normal') {
            delete dates[date.value];
            delete notes[date.value];
        } else {
            dates[date.value] = type.value === 'special' ? selectedWindows : [];
            notes[date.value] = note.value.trim();
        }
        dirty = false;
        sync();
        renderMonth();
        status.textContent = 'Alteração incluída na proposta. Envie para validação quando terminar.';
    });
    read('import').addEventListener('click', () => {
        const year = read('year').value;
        if (!loadDay(date.value)) return;
        let total = 0;
        Object.entries(holidays).forEach(([day, name]) => {
            if (!day.startsWith(year + '-') || Object.hasOwn(dates, day)) return;
            dates[day] = [];
            notes[day] = name;
            total++;
        });
        month.value = year + (month.value.slice(4) || '-01');
        loadDay(month.value + '-01');
        sync();
        renderMonth();
        status.textContent = total + ' datas adicionadas à proposta de ' + year + '. Revise a cobertura antes de enviar.';
    });
    editor.closest('form').addEventListener('submit', (event) => {
        if (!dirty) return;
        event.preventDefault();
        status.textContent = 'Adicione a edição da data à proposta antes de enviar.';
        read('save').focus();
    });
    editor.hidden = false;
    editor.closest('form').querySelector('[type=submit]').disabled = false;
}
