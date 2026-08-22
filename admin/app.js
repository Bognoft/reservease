let token = localStorage.getItem('reservease_admin_token') || '';
let tables = [];
let reservations = [];
let waitlist = [];

const $ = (id) => document.getElementById(id);

const colors = {
    available: '#75c69a',
    reserved: '#e3a857',
    occupied: '#79aee8'
};

async function api(action, opts = {}) {
    opts.headers = {
        ...(opts.headers || {}),
        ...(token
            ? { Authorization: 'Bearer ' + token }
            : {})
    };

    const response = await fetch(
        '../api/index.php?action=' + action,
        opts
    );

    const data = await response.json();

    if (response.status === 401) {
        logout();
        throw new Error(data.error);
    }

    if (!response.ok) {
        throw new Error(
            data.error || 'Request failed'
        );
    }

    return data;
}

async function login() {
    const msg = $('loginMsg');

    try {
        const data = await api('admin-login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: $('user').value,
                password: $('pass').value
            })
        });

        token = data.token;

        localStorage.setItem(
            'reservease_admin_token',
            token
        );

        await start(data.username);

    } catch (error) {
        msg.innerHTML =
            '<div class="notice">' +
            error.message +
            '</div>';
    }
}

async function start(username) {
    $('login').classList.add('hidden');
    $('app').classList.remove('hidden');

    $('who').textContent =
        'Signed in as ' + username;

    await refresh();
}

function logout() {
    localStorage.removeItem(
        'reservease_admin_token'
    );

    token = '';

    $('app').classList.add('hidden');
    $('login').classList.remove('hidden');
}

async function refresh() {
    const [tablesData, reservationsData, waitlistData] =
        await Promise.all([
            api('tables'),
            api('reservations'),
            api('waitlist')
        ]);

    tables = tablesData.tables;
    reservations = reservationsData.reservations;
    waitlist = waitlistData.waitlist;

    renderFloor();
    renderReservations();
    renderWaitlist();

    $('total').textContent =
        tables.length;

    $('available').textContent =
        tables.filter(
            table => table.status === 'available'
        ).length;

    $('reserved').textContent =
        tables.filter(
            table => table.status === 'reserved'
        ).length;

    $('occupied').textContent =
        tables.filter(
            table => table.status === 'occupied'
        ).length;
}

function renderFloor() {
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

            <g>
                <rect
                    class="table-shape"
                    x="${x}"
                    y="${y}"
                    width="${width}"
                    height="${height}"
                    rx="16"
                    fill="${colors[table.status]}"
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

    $('adminFloor').innerHTML = svg;
}

function show(id, btn) {
    document
        .querySelectorAll('.view')
        .forEach(view => {
            view.classList.add('hidden');
        });

    $(id).classList.remove('hidden');

    document
        .querySelectorAll('.tabs button')
        .forEach(button => {
            button.classList.remove('active');
        });

    btn.classList.add('active');

    if (id === 'reservations') {
        renderReservations();
    }
}

function renderReservations() {
    const search =
        ($('search')?.value || '')
            .toLowerCase();

    const filteredReservations =
        reservations.filter(reservation => {
            const text =
                reservation.guest_name +
                ' ' +
                reservation.phone +
                ' ' +
                reservation.code +
                ' T' +
                reservation.table_id;

            return text
                .toLowerCase()
                .includes(search);
        });

    $('resBody').innerHTML =
        filteredReservations
            .map(reservation => `
                <tr>
                    <td>
                        <b>
                            ${reservation.guest_name}
                        </b>

                        <br>

                        <span class="muted">
                            ${reservation.phone}
                        </span>
                    </td>

                    <td>
                        T${reservation.table_id}
                    </td>

                    <td>
                        ${reservation.party_size}
                    </td>

                    <td>
                        ${reservation.reservation_date}

                        <br>

                        ${reservation.time_slot}
                    </td>

                    <td>
                        <span
                            class="pill ${reservation.status}"
                        >
                            ${reservation.status}
                        </span>
                    </td>

                    <td>
                        ${
                            reservation.status === 'upcoming'
                                ? `
                                    <button
                                        class="btn primary"
                                        onclick="action(
                                            '${reservation.code}',
                                            'arrive'
                                        )"
                                    >
                                        Arrive
                                    </button>

                                    <button
                                        class="btn danger"
                                        onclick="action(
                                            '${reservation.code}',
                                            'cancel'
                                        )"
                                    >
                                        Cancel
                                    </button>
                                `
                                : reservation.status === 'arrived'
                                    ? `
                                        <button
                                            class="btn primary"
                                            onclick="action(
                                                '${reservation.code}',
                                                'release'
                                            )"
                                        >
                                            Release
                                        </button>
                                    `
                                    : '—'
                        }
                    </td>
                </tr>
            `)
            .join('')

        || `
            <tr>
                <td colspan="6">
                    No reservations found.
                </td>
            </tr>
        `;
}

function renderWaitlist() {
    $('waitBody').innerHTML =
        waitlist
            .map(wait => `
                <tr>
                    <td>
                        <b>
                            ${wait.name}
                        </b>

                        <br>

                        <span class="muted">
                            ${wait.phone}
                        </span>
                    </td>

                    <td>
                        ${wait.party_size}
                    </td>

                    <td>
                        ${wait.reservation_date}

                        <br>

                        ${wait.time_slot}
                    </td>

                    <td>
                        <span
                            class="pill ${wait.status}"
                        >
                            ${wait.status}
                        </span>
                    </td>
                </tr>
            `)
            .join('')

        || `
            <tr>
                <td colspan="4">
                    No waitlist entries.
                </td>
            </tr>
        `;
}

async function action(code, type) {
    try {
        await api('admin-action', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                code: code,
                type: type
            })
        });

        await refresh();

    } catch (error) {
        alert(error.message);
    }
}

(async () => {
    if (token) {
        try {
            const data =
                await api('admin-check');

            await start(data.username);

        } catch (error) {
        }
    }
})();