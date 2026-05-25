console.log('Treasury Revenue System starter loaded');

async function apiGet(url) {
    const response = await fetch(url, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
    });
    const text = await response.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch (e) {
        console.error('Non-JSON response from:', url);
        console.error(text);
        throw new Error(`Server returned non-JSON response for ${url}`);
    }
    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Request failed');
    }
    return data;
}

async function apiPost(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Request failed');
    }
    return data;
}

function showMessage(targetId, message, type = 'success') {
    const el = document.getElementById(targetId);
    if (!el) return;
    el.className = `alert alert-${type}`;
    el.textContent = message;
    el.style.display = 'block';
}

function clearMessage(targetId) {
    const el = document.getElementById(targetId);
    if (!el) return;
    el.textContent = '';
    el.style.display = 'none';
}

// ─────────────────────────────────────────────────────────────────────────────
// TablePager — shared pagination + sorting for all master-data tables
// Usage:
//   window.pager = new TablePager({ tbodyId, paginationId, colCount, renderRow });
//   window.pager.setData(rows);   // call after data loads or search changes
// ─────────────────────────────────────────────────────────────────────────────
class TablePager {
    constructor({ tbodyId, paginationId, colCount, renderRow }) {
        this.tbody       = document.getElementById(tbodyId);
        this.paginEl     = document.getElementById(paginationId);
        this.colCount    = colCount;
        this.renderRow   = renderRow;
        this.all         = [];   // full dataset
        this.page        = 1;
        this.pageSize    = 10;
        this.sortCol     = null;
        this.sortDir     = 'asc';

        // Sort on th[data-col] click
        document.querySelectorAll('th[data-col]').forEach(th => {
            th.style.cursor     = 'pointer';
            th.style.userSelect = 'none';
            th.style.whiteSpace = 'nowrap';
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (this.sortCol === col) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortCol = col;
                    this.sortDir = 'asc';
                }
                this.page = 1;
                this.render();
            });
        });

        // Pagination button clicks via event delegation
        if (this.paginEl) {
            this.paginEl.addEventListener('click', e => {
                const g = e.target.closest('[data-goto]');
                const s = e.target.closest('[data-size]');
                if (g) this.goTo(parseInt(g.dataset.goto));
                if (s) this.setPageSize(parseInt(s.dataset.size));
            });
        }
    }

    setData(rows) {
        this.all  = rows;
        this.page = 1;
        this.render();
    }

    goTo(p) { this.page = p; this.render(); }

    setPageSize(n) { this.pageSize = n; this.page = 1; this.render(); }

    _sorted() {
        if (!this.sortCol) return [...this.all];
        const col = this.sortCol, dir = this.sortDir;
        return [...this.all].sort((a, b) => {
            let va = a[col] ?? '', vb = b[col] ?? '';
            const na = parseFloat(va), nb = parseFloat(vb);
            if (!isNaN(na) && !isNaN(nb)) { va = na; vb = nb; }
            else { va = String(va).toLowerCase(); vb = String(vb).toLowerCase(); }
            if (va < vb) return dir === 'asc' ? -1 : 1;
            if (va > vb) return dir === 'asc' ?  1 : -1;
            return 0;
        });
    }

    render() {
        const sorted     = this._sorted();
        const total      = sorted.length;
        const totalPages = Math.ceil(total / this.pageSize) || 1;
        this.page        = Math.min(this.page, totalPages);

        if (total === 0) {
            this.tbody.innerHTML = `<tr><td colspan="${this.colCount}" class="text-center py-4 text-muted">No records found.</td></tr>`;
            if (this.paginEl) this.paginEl.innerHTML = '';
            this._renderSortIndicators();
            return;
        }

        const start = (this.page - 1) * this.pageSize;
        this.tbody.innerHTML = sorted.slice(start, start + this.pageSize).map(r => this.renderRow(r)).join('');

        this._renderPagination(total, totalPages);
        this._renderSortIndicators();
    }

    _renderPagination(total, totalPages) {
        if (!this.paginEl) return;

        const from     = (this.page - 1) * this.pageSize + 1;
        const to       = Math.min(this.page * this.pageSize, total);
        const showAll  = total <= 100;
        const show100  = total >  100;

        const sizeBtn = (n, label) => {
            const active = (n === 0 ? this.pageSize >= total : this.pageSize === n);
            const size   = n === 0 ? total : n;
            return `<button class="btn btn-xs ${active ? 'btn-primary' : 'btn-outline-secondary'}" data-size="${size}" style="padding:1px 8px;font-size:.72rem;">${label}</button>`;
        };

        let html = `
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2 border-top bg-white" style="font-size:.8rem;">
          <div class="d-flex align-items-center gap-2 text-muted flex-wrap">
            <span style="font-size:.78rem;">Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> records</span>
            <span class="text-muted">|</span>
            <span class="text-muted" style="font-size:.72rem;">Per page:</span>
            ${sizeBtn(10,  '10')}
            ${sizeBtn(25,  '25')}
            ${sizeBtn(50,  '50')}
            ${showAll ? sizeBtn(0, 'All') : ''}
            ${show100 ? sizeBtn(100, '100') : ''}
          </div>`;

        if (totalPages > 1) {
            const pages = this._visiblePages(totalPages);
            let last = 0, nav = '';
            pages.forEach(p => {
                if (p - last > 1) nav += `<li class="page-item disabled"><span class="page-link" style="font-size:.75rem;padding:3px 7px;">…</span></li>`;
                nav += `<li class="page-item ${p === this.page ? 'active' : ''}">
                    <button class="page-link" data-goto="${p}" style="font-size:.75rem;padding:3px 9px;min-width:32px;text-align:center;">${p}</button>
                  </li>`;
                last = p;
            });

            html += `
          <nav>
            <ul class="pagination pagination-sm mb-0" style="gap:2px;">
              <li class="page-item ${this.page === 1 ? 'disabled' : ''}">
                <button class="page-link" data-goto="${this.page - 1}" style="font-size:.75rem;padding:3px 9px;">‹</button>
              </li>
              ${nav}
              <li class="page-item ${this.page === totalPages ? 'disabled' : ''}">
                <button class="page-link" data-goto="${this.page + 1}" style="font-size:.75rem;padding:3px 9px;">›</button>
              </li>
            </ul>
          </nav>`;
        }

        html += `</div>`;
        this.paginEl.innerHTML = html;
    }

    _visiblePages(totalPages) {
        const delta = 2;
        const pages = new Set([1, totalPages]);
        for (let i = Math.max(2, this.page - delta); i <= Math.min(totalPages - 1, this.page + delta); i++) pages.add(i);
        return [...pages].sort((a, b) => a - b);
    }

    _renderSortIndicators() {
        document.querySelectorAll('th[data-col]').forEach(th => {
            th.querySelector('.sort-ic')?.remove();
            const ic  = document.createElement('span');
            ic.className = 'sort-ic ms-1';
            const col = th.dataset.col;
            ic.style.opacity  = col === this.sortCol ? '1' : '0.25';
            ic.style.fontSize = '.7rem';
            ic.innerHTML      = col === this.sortCol ? (this.sortDir === 'asc' ? '▲' : '▼') : '⇅';
            th.appendChild(ic);
        });
    }
}
