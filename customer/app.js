let tables = [];
let selected = null;

const $ = (id) => document.getElementById(id);

const colors = {
    available: '#75c69a',
    reserved: '#e3a857',
    occupied: '#79aee8'
};

async function load() {
    const response = await fetch(
        '../api/index.php?action=tables'
    );

    const data = await response.json();

    tables = data.tables || [];

    render();

    $('date').value =
        new Date().toISOString().slice(0, 10);
}

function render() {
    let svg = `
        <rect
            x="250"
            y="30"
            width="420"
            height="55"
            rx="12"
            fill="#203027"
        />

        <text
            x="460"
            y="64"
            text-anchor="middle"
            fill="#e3a857"
            font-weight="800"
        >
            BUFFET AREA
        </text>
    `;

    for (const table of tables) {
        const x = +table.x;
        const y = +table.y;
        const width = +table.width;
        const height = +table.height;
        const color = colors[table.status];
        const isSelected = selected == table.id;

        let chairs = '';

        for (let i = 0; i < 4; i++) {
            const cx =
                x +
                width / 2 +
                (i % 2
                    ? width / 2 + 10
                    : -width / 2 - 10);

            const cy =
                y +
                height / 2 +
                (i < 2
                    ? -height * 0.25
                    : height * 0.25);

            chairs += `
                <circle
                    class="chair"
                    cx="${cx}"
                    cy="${cy}"
                    r="8"
                />
            `;
        }

        svg += `
            ${chairs}

            <g
                onclick="pick(${table.id})"
                style="cursor:${
                    table.status === 'available'
                        ? 'pointer'
                        : 'default'
                }"
            >

                <rect
                    class="table-shape"
                    x="${x}"
                    y="${y}"
                    width="${width}"
                    height="${height}"
                    rx="16"
                    fill="${color}"
                    opacity="${isSelected ? 1 : 0.88}"
                />

                <text
                    class="table-label"
                    x="${x + width / 2}"
                    y="${y + height / 2}"
                    text-anchor="middle"
                >
                    ${table.name}
                </text>

                <text
                    class="seat-label"
                    x="${x + width / 2}"
                    y="${y + height / 2 + 18}"
                    text-anchor="middle"
                >
                    ${table.seats} seats
                </text>

            </g>
        `;
    }

    $('floor').innerHTML = svg;
}

function pick(id) {
    const table = tables.find(
        item => item.id == id
    );

    if (table?.status === 'available') {
        selected = id;
        render();
    }
}

async function reserve() {
    const msg = $('msg');

    msg.className = '';

    if (!selected) {
        msg.innerHTML = `
            <div class="notice">
                Select an available table first.
            </div>
        `;

        return;
    }

    const body = {
        table_id: selected,
        name: $('name').value.trim(),
        phone: $('phone').value.trim(),
        party_size: +$('party').value,
        date: $('date').value,
        time_slot: $('time').value,
        payment_method: $('payment').value
    };

    if (
        !body.name ||
        !body.phone ||
        !body.date
    ) {
        msg.innerHTML = `
            <div class="notice">
                Complete all required fields.
            </div>
        `;

        return;
    }

    const response = await fetch(
        '../api/index.php?action=reserve',
        {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json'
            },

            body: JSON.stringify(body)
        }
    );

    const data = await response.json();

    if (!response.ok) {
        msg.innerHTML = `
            <div class="notice">
                ${data.error}
            </div>
        `;

        return;
    }

    msg.innerHTML = `
        <div class="notice">
            Reservation confirmed.
            Your code is
            <b>${data.code}</b>.
        </div>
    `;

    selected = null;

    load();
}

async function joinWaitlist() {
    const msg = $('waitMsg');

    const body = {
        name: $('name').value.trim(),
        phone: $('phone').value.trim(),
        party_size: +$('party').value,
        date: $('date').value,
        time_slot: $('time').value,
        note: $('note').value.trim()
    };

    if (
        !body.name ||
        !body.phone ||
        !body.date
    ) {
        msg.innerHTML = `
            <div class="notice">
                Complete your name, phone,
                and date first.
            </div>
        `;

        return;
    }

    const response = await fetch(
        '../api/index.php?action=waitlist-add',
        {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json'
            },

            body: JSON.stringify(body)
        }
    );

    const data = await response.json();

    msg.innerHTML = `
        <div class="notice">
            ${
                response.ok
                    ? data.message
                    : data.error
            }
        </div>
    `;
}

load();