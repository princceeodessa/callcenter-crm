// CRM БлагоДар → сводка владельца callcenter-crm: строки договоров, расход которых БлагоДар не посчитала
// (работа без ставки монтажникам, товар без цены закупки в 1С). ТОЛЬКО ЧТЕНИЕ: тот же расчёт, что её отчёт
// «Прибыль договоров» (ContractProfitService), — без заказчиков, адресов и номеров договоров. В самой БлагоДар
// ничего не меняется. Досчитывает эти строки сводка (App\Services\Owner\ContractProfitFill) — своими ставками.
//
// Запуск — systemd callcenter-crm-cp-uncosted (пользователь blagodar, каталог /opt/blagodar-crm/apps/api, её .env):
//   node /var/www/callcenter-crm/scripts/blagodar/cp_uncosted.js /var/lib/callcenter-crm-owner/cp_uncosted.json
'use strict';

const fs = require('fs');
const path = require('path');

const API = process.env.BLAGODAR_API_DIR || '/opt/blagodar-crm/apps/api';
const { PrismaService } = require(path.join(API, 'dist/common/prisma/prisma.service'));
const { ContractProfitService } = require(path.join(API, 'dist/modules/contract-profit/contract-profit.service'));
const { cpRateOn } = require(require.resolve('@blagodar/shared', { paths: [API] }));

// так БлагоДар помечает строку, расход которой не посчитан (работа без ставки, товар без цены закупки)
const NOT_COUNTED = 'расход не посчитан';
// средняя доля расхода в цене строки — по договорам последних 120 дней
const SHARE_DAYS = 120;

const num = (v) => Number(v) || 0;
const money = (v) => (Math.round(v * 100) / 100).toFixed(2);
const localDay = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

(async () => {
    const out = process.argv[2];
    if (!out) throw new Error('нужен путь файла выгрузки');
    const prisma = new PrismaService();
    await prisma.$connect();
    try {
        const svc = new ContractProfitService(prisma);
        const [all, rateRows] = await Promise.all([svc.all(), svc.rateRows()]);
        const rates = rateRows.map((r) => ({ id: r.id, kind: r.kind, base: r.base ?? null, value: r.value.toString(), validFrom: r.validFrom, validTo: r.validTo }));
        const since = localDay(new Date(Date.now() - SHARE_DAYS * 86400000));

        const share = { work: [0, 0], goods: [0, 0] };
        const contracts = [];
        for (const c of all) {
            const lines = c.lines || [];
            if (c.date >= since) {
                for (const l of lines) {
                    // «Монтаж потолочного покрытия» с полотном — другая доля, его не смешиваем
                    if (l.cost === null || (l.costNote || '').includes(NOT_COUNTED) || (l.kind === 'work' && l.canvas)) continue;
                    share[l.kind][0] += num(l.cost);
                    share[l.kind][1] += num(l.sum);
                }
            }
            if (c.complete) continue;
            const m = c.missing;
            const usn = cpRateOn(rates, 'TAX_USN', c.date, c.base);
            const min = cpRateOn(rates, 'TAX_MIN', c.date, c.base);
            contracts.push({
                date: c.date,
                measurer: c.measurer || null,
                revenue: c.revenue,
                // УСН — «доходы минус расходы» с минимальным налогом: досчитанный расход его уменьшает
                income: money(num(c.revenue) - num(c.vat)),
                before_tax: money(num(c.costs) - num(c.vat) - num(c.usn)),
                usn: c.usn,
                usn_pct: usn ? num(usn.value) : null,
                min_pct: min ? num(min.value) : null,
                // не хватает другого (материалы норм, полотно, ставки отделов и налогов) — этого сводка не досчитывает
                other_missing: m.materials.length > 0 || m.canvas || m.rates.length > 0,
                lines: lines.filter((l) => (l.costNote || '').includes(NOT_COUNTED))
                    .map((l) => ({ kind: l.kind, name: l.name, unit: l.unit, qty: num(l.qty), sum: l.sum })),
            });
        }
        const ratio = (k) => (share[k][1] > 0 ? Math.round((share[k][0] / share[k][1]) * 10000) / 10000 : null);
        const json = JSON.stringify({
            generated_at: new Date().toISOString(),
            shares: { work: ratio('work'), goods: ratio('goods'), since },
            contracts,
        });
        const tmp = `${out}.tmp`;
        fs.writeFileSync(tmp, json, { mode: 0o640 });
        fs.renameSync(tmp, out);
        console.log(`cp_uncosted: ${contracts.length} неполных договоров, ${Buffer.byteLength(json)} байт`);
    } finally {
        await prisma.$disconnect();
    }
})().catch((e) => {
    console.error('cp_uncosted: ' + (e && e.message));
    process.exit(1);
});
