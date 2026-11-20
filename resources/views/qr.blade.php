
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obyekt Passporti</title>
    <link rel="stylesheet" href="/css/qr/main.css">

    <style>
        * {
            color: #262626;
        }

        .font-inter {
            font-family: 'Inter', serif;
        }

        .font-anton {
            font-family: 'Anton', sans-serif;
            font-weight: 400;
        }

        .fs-14 {
            font-size: 14px;
        }

        .fs-20 {
            font-size: 20px;
        }

        .fw-medium {
            font-weight: 500;
        }

        .text-light2 {
            color: #A8A8A8;
        }

        .bottom-border {
            border-bottom: 1px solid #EEEEEE;
        }

        html,
        body {
            overflow-x: hidden;
        }

        body {
            font-family: "Roboto", sans-serif;
            background-color: rgb(244, 244, 244);
            color: #262626;
        }

        main {
            max-width: 786px;
            margin: 0 auto;
            background-color: #fff;
            min-height: 100vh;
        }

        .top-block {
            background: url("/images/qr/bg.jpg") no-repeat center;
            background-size: cover;
        }

        .middle-block {
            padding: 12px;
            padding-top: 70px;
        }

        .countdown-wrapper {
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 24px);
            max-width: 500px;
        }

        .countdown-number {
            color: #1773EA;
            font-size: 30px;
            line-height: 24px;
            font-family: 'Anton', sans-serif;
            letter-spacing: 2px;
        }

        .countdown-text {
            color: #BFBFBF;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .table {
            width: 100%;
        }

        .table th,
        .table td {
            padding: 3px;
        }

        .table.table-bordered {
            border-collapse: collapse;
        }

        .table.table-bordered,
        .table.table-bordered th,
        .table.table-bordered td {
            border: 1px solid #222;
        }

        .table.table-borderless,
        .table.table-borderless th,
        .table.table-borderless td {
            border: none;
        }

        .table {
            width: 100%;
        }
        .text-sm{
            font-size: 14px;
        }
        .text-xs{
            font-size: 12px;
        }
        .btn{
            border: none;
            border-radius: 20px;
            color: white;
            background-color: #1773ea;
            font-size: 12px;
            padding: 2px 12px;
        }

    </style>
</head>
<body>
<main class="d-flex flex-column">
    <section
        class="top-block d-flex align-items-center justify-content-between px-3 pt-4 pb-5"
    >
      <span
          class="text-white font-inter fw-bold fs-20"
          style="letter-spacing: 2%;"
      >
        ID: {{ $object->task_id }}
      </span>
        <a
            href="#!"
            target=""
            class="d-flex align-items-center justify-content-center rounded-circle"
            style="height: 36px; width: 36px; background-color: rgba(255,255,255, 0.1);"
        >
            <svg
                width="20"
                height="20"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M7.24996 8.91671L12.75 6.08337M7.24996 11.0834L12.75 13.9167M7.5 10C7.5 11.3807 6.38071 12.5 5 12.5C3.61929 12.5 2.5 11.3807 2.5 10C2.5 8.61929 3.61929 7.5 5 7.5C6.38071 7.5 7.5 8.61929 7.5 10ZM17.5 5C17.5 6.38071 16.3807 7.5 15 7.5C13.6193 7.5 12.5 6.38071 12.5 5C12.5 3.61929 13.6193 2.5 15 2.5C16.3807 2.5 17.5 3.61929 17.5 5ZM17.5 15C17.5 16.3807 16.3807 17.5 15 17.5C13.6193 17.5 12.5 16.3807 12.5 15C12.5 13.6193 13.6193 12.5 15 12.5C16.3807 12.5 17.5 13.6193 17.5 15Z"
                    stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </section>
    <section class="position-relative middle-block flex-fill">
        <div class="countdown-wrapper position-absolute d-flex flex-column align-items-center  rounded-3 py-2 px-5"
             style="background-color: #F8F8FB; box-shadow: 4px 2px 16px 0px #0500381F;">
            <p class="mb-1 font-inter fw-medium fs-12 text-uppercase text-nowrap">Qurilish yakunlanishiga</p>
            <div class="w-100 d-flex justify-content-evenly">
                <div class="d-flex flex-column justify-content-center align-items-center">
                    <span class="countdown-number" id="days">0</span>
                    <span class="countdown-text">Kun</span>
                </div>
                <span class="fw-medium" style="color: #BFBFBF">:</span>
                <div class="d-flex flex-column justify-content-center align-items-center">
                    <span class="countdown-number" id="hours">0</span>
                    <span class="countdown-text">Soat</span>
                </div>
                <span class="fw-medium" style="color: #BFBFBF">:</span>
                <div class="d-flex flex-column justify-content-center align-items-center">
                    <span class="countdown-number" id="minutes">0</span>
                    <span class="countdown-text">Minut</span>
                </div>
            </div>
        </div>
        <div class="bottom-border py-2">
            <span class="fs-14 text-light2 font-inter">Obyekt nomi:</span>
            <p class="mb-0 fs-14 fw-medium">{{ $object->name }}</p>
        </div>
        <div class="bottom-border py-2">
            <span class="fs-14 text-light2 font-inter">Qurilishga ruxsat berilgan sana:</span>
            <p class="mb-0 fs-14 fw-medium">{{ $object->created_at }}</p>
        </div>
        <div class="bottom-border py-2">
            <span class="fs-14 text-light2 font-inter">Buyurtmachi:</span>
            <p class="mb-0 fs-14 fw-medium">{{ $object->users()->where('role_id', \App\Enums\UserRoleEnum::BUYURTMACHI->value)->first()->organization_name ?? $object->users()->where('role_id', \App\Enums\UserRoleEnum::BUYURTMACHI->value)->first()?->full_name }}</p>
        </div>
        <div class="bottom-border py-2">
            <span class="fs-14 text-light2 font-inter">Loyihachi:</span>
            <p class="d-flex align-items-center mb-0 fs-14 fw-medium">
          <span
              class="d-flex align-items-center justify-content-center rounded-circle me-2"
              style="height: 40px; width: 40px; background-color: #F6F8F9; color: #ECA235"
          >{{$rating_loyiha}}</span>
                {{ $object->users()->where('role_id', \App\Enums\UserRoleEnum::LOYIHA->value)->first()?->full_name ?? $object->users()->where('role_id', \App\Enums\UserRoleEnum::LOYIHA->value)->first()?->organization_name }}
            </p>
        </div>
        <div class="bottom-border py-2">
            <span class="fs-14 text-light2 font-inter">Pudrat tashkiloti:</span>
            <p class="d-flex align-items-center mb-0 fs-14 fw-medium">
                @if($rating_umumiy != null)
                    <span
                        class="d-flex align-items-center justify-content-center rounded-circle me-2"
                        style="height: 40px; width: 40px; background-color: #F6F8F9; color: #80C726"
                    >
                    {{ $rating_umumiy }}
                  </span>
                @endif
                @if($rating_mel != null)
                    <span
                        class="d-flex align-items-center justify-content-center rounded-circle me-2"
                        style="height: 40px; width: 40px; background-color: #F6F8F9; color: #ECA235"
                    >
                        {{ $rating_mel }}
                    </span>
                @endif
                {{ $object->users()->where('role_id', \App\Enums\UserRoleEnum::QURILISH->value)->first()?->full_name ?? $object->users()->where('role_id', \App\Enums\UserRoleEnum::QURILISH->value)->first()?->organization_name }}
            </p>
        </div>
        <div class="bottom-border" style="padding: 12px 0;">
            <span class="fs-14 text-light2 font-inter">Manzili:</span>
            <div class="d-flex alifn-items-center justify-content-between">
                <p class="mb-0 fs-14 fw-medium">{{ $object->location_building }}</p>
                <a
                    href="http://maps.google.com/maps?q={{ $object->lat }},{{ $object->long }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <svg width="22" height="28" viewBox="0 0 22 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="6" y="26" width="10" height="2" rx="1" fill="#525252" fill-opacity="0.25"/>
                        <path d="M22 11.5715C22 17.9622 11 27 11 27C11 27 0 17.9622 0 11.5715C0 5.1807 4.92487 0 11 0C17.0751 0 22 5.1807 22 11.5715Z" fill="#DA4747"/>
                        <ellipse cx="11.0002" cy="11.1708" rx="4.58333" ry="4.65517" fill="white"/>
                    </svg>
                </a>
            </div>
        </div>

        <table class="table" style="margin-top: 15px;">
            <tbody>
                <tr>
                    <td class="text-sm lh-sm">Ekspertiza xulosasining reestr raqami va hujjatlari:</td>
                    <td style="width: 20%" class="text-xs text-light2">{{ $object->reestr_number }}</td>
                    <td class="text-end" style="width: 20%">
                        <button class="btn text-nowrap" type="button" onclick="fetchEkspertiza()">Ko'chirib olish</button>
                    </td>
                </tr>
                @if($object->object_type_id == 2)
                    <tr>
                        <td class="text-sm lh-sm">Arxitektura-shaxarsozlik kengashi xulosasi raqami va hujjatlari:</td>
                        <td class="text-xs text-light2">{{ $object->number_protocol }}</td>
                        <td class="text-end" style="width: 20%">
                            <button class="btn" type="button" onclick="fetchApz()">Ko'chirib olish</button>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td class="text-sm lh-sm">Obyekt ro‘yxatdan o‘tkazilganligi haqida
                        ko'chirma</td>
                    <td class="text-xs text-light2">{{ $object->task_id }}</td>
                    <td class="text-end" style="width: 20%">
                        <button class="btn" type="button" onclick="loadPdf()">Ko'chirib olish</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="py-3">
            <p class="mb-2 fs-14 font-inter" style="color: #51627B;">&copy;{{ date('Y') }}. Barcha huquqlar
                himoyalangan.</p>
            <span>
          <img width="113" src="/images/qr/shaffof.svg" alt="">
        </span>
        </div>
    </section>
</main>

<script>
    const endDate = new Date("{{ \Carbon\Carbon::parse($object->deadline)->format('Y-m-d') . "T00:00:00" }}").getTime();
    const taskId = "{{ $object->task_id ?? $object->manual_task_id }}"
    function showAlert(message, type = "info") {
        // agar oldingi alert bo‘lsa — o‘chiramiz
        const old = document.getElementById("custom-alert");
        if (old) old.remove();

        const alert = document.createElement("div");
        alert.id = "custom-alert";

        const colors = {
            success: "#198754",
            error: "#dc3545",
            warning: "#ffc107",
            info: "#0d6efd"
        };

        alert.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    min-width: 250px;
    padding: 14px 18px;
    color: white;
    background: ${colors[type]};
    border-radius: 8px;
    font-family: Arial, sans-serif;
    box-shadow: 0 8px 20px rgba(0,0,0,.2);
    z-index: 9999;
  `;

        alert.innerHTML = `
    ${message}
  `;

        document.body.appendChild(alert);

        // 3 soniyadan keyin yopiladi
        setTimeout(() => {
            alert.remove();
        }, 3000);
    }

    function fetchApz() {
        fetch(`https://api.dx.mc.uz/api/module2/v1/tasks/{{ $object->number_protocol }}/download-conclusion-file`,)
            .then(res => {
                if(!res?.ok){
                    throw new Error('Ma\'lumot topilmadi');

                }else{
                    return res?.blob()
                }
            })
            .then(data => {
                const url = URL.createObjectURL(data);
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('target', '_blank');
                link.setAttribute('download', "apz_{{ $object->number_protocol }}");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }).catch(() => {
                showAlert('Ma\'lumot topilmadi', 'error');
            });
    }

    function fetchEkspertiza() {
        fetch(`{{$reestr}}`)
            .then(res => {
                if(!res?.ok){
                    throw new Error('Ma\'lumot topilmadi');
                }else{
                    return res?.blob()
                }
            })
            .then(data => {
                console.log(data)
                const url = URL.createObjectURL(data);
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('target', '_blank');
                link.setAttribute('download', "ekspertiza_xulosa_{{ $object->reestr_number }}");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }).catch(() => {
            showAlert('Ma\'lumot topilmadi', 'error');
        });
    }

    function loadPdf() {
        fetch(`https://api-nazorat.mc.uz/api/get-object-pdf/${taskId}`)
            .then(res => res.json())
            .then(data => {
                if(!!data){
                    const base64 = data.result.data.file;

                    const byteCharacters = atob(base64);
                    const byteNumbers = new Array(byteCharacters.length);
                    for (let i = 0; i < byteCharacters.length; i++) {
                        byteNumbers[i] = byteCharacters.charCodeAt(i);
                    }

                    const byteArray = new Uint8Array(byteNumbers);
                    const blob = new Blob([byteArray], { type: 'application/pdf' });

                    const url = URL.createObjectURL(blob);
                    window.open(url); // yangi tabda ochiladi
                }else{
                    showAlert('Ma\'lumot topilmadi', 'error')
                }

            }).catch(e => {
            showAlert('Ma\'lumot topilmadi', 'error')

        });
    }

    const timer = setInterval(() => {
        const now = new Date().getTime();
        let distance = endDate - now;

        let days = 0, hours = 0, minutes = 0;

        if (distance >= 0) {
            days = Math.floor(distance / (1000 * 60 * 60 * 24));
            hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        } else {
            distance = Math.abs(distance);

            const d = Math.floor(distance / (1000 * 60 * 60 * 24));
            const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

            if (d > 0) {
                days = -d;
                hours = h;
                minutes = m;
            } else if (h > 0) {
                days = 0;
                hours = -h;
                minutes = m;
            } else {
                days = 0;
                hours = 0;
                minutes = -m;
            }
        }

        document.getElementById('days').innerHTML = days;
        document.getElementById('hours').innerHTML = hours;
        document.getElementById('minutes').innerHTML = minutes;

    }, 1000);



</script>
</body>
</html>
