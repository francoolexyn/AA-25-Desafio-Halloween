'use strict';
const page = document.body.dataset.page;
let session = { usuario: null, csrf: '' };
const $ = (selector) => document.querySelector(selector);
function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}
function message(text, error = false) {
  const box = $('#message');
  box.textContent = text; box.className = 'message' + (error ? ' error' : ''); box.hidden = false;
}
async function api(route, method = 'GET', body) {
  const headers = {};
  let actualMethod = method;
  if (method !== 'GET') headers['X-CSRF-Token'] = session.csrf;
  if (body instanceof FormData) {
    if (method === 'PUT') { actualMethod = 'POST'; headers['X-HTTP-Method-Override'] = 'PUT'; }
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'; body = JSON.stringify(body);
  }
  const response = await fetch('api/index.php?ruta=' + encodeURIComponent(route), { method: actualMethod, headers, body, credentials: 'same-origin' });
  const data = await response.json().catch(() => { throw new Error('Respuesta inválida del servidor. Verificá que PHP esté activo y que la carga no exceda post_max_size.'); });
  if (!response.ok) throw new Error(data.error || 'No se pudo completar la operación.');
  return data;
}
function navigation() {
  const nav = $('#navigation'); if (!nav || !session.usuario) return;
  nav.replaceChildren(el('span', 'user-name', session.usuario.nombre));
  if (session.usuario.rol === 'admin' && page !== 'admin') {
    const a = el('a', '', 'Administrar'); a.href = 'admin.php'; nav.append(a);
  }
  const logout = el('button', 'secondary', 'Salir');
  logout.onclick = async () => {
    logout.disabled = true;
    try { await api('logout', 'POST', {}); location.href = 'index.html'; }
    catch (e) { message(e.message, true); logout.disabled = false; }
  }; nav.append(logout);
}
async function gallery() {
  const grid = $('#costumes');
  try {
    const { disfraces } = await api('disfraces');
    grid.replaceChildren();
    $('#stats').textContent = `${disfraces.length} disfraces · ${disfraces.reduce((n, d) => n + Number(d.votos), 0)} votos`;
    if (!disfraces.length) grid.append(el('p', 'empty', 'La pasarela se está preparando. Pronto aparecerán los disfraces.'));
    disfraces.forEach((d, i) => {
      const card = el('article', 'card');
      const image = el('div', 'card-image');
      if (d.foto) {
        const img = el('img'); img.src = `api/index.php?ruta=disfraces/${d.id}/foto`; img.alt = d.nombre; img.loading = 'lazy'; image.append(img);
      } else {
        const symbol = el('span', 'costume-symbol', ['🧙','🧛','👻','🎃','🦇'][Number(d.id - 1) % 5]); symbol.setAttribute('aria-hidden', 'true'); image.append(symbol);
      }
      image.append(el('span', 'card-number', `CRIATURA ${String(i + 1).padStart(2, '0')}`));
      const body = el('div', 'card-body'); body.append(el('h3', '', d.nombre), el('p', '', d.descripcion));
      const actions = el('div', 'card-actions'), count = el('span', 'vote-count');
      count.append(el('strong', '', d.votos), document.createTextNode(Number(d.votos) === 1 ? 'voto' : 'votos'));
      const voted = Boolean(Number(d.votado));
      const button = el('button', 'button small', voted ? 'Votado ✓' : 'Votar ♡'); button.disabled = voted;
      button.setAttribute('aria-label', (voted ? 'Ya votaste por ' : 'Votar por ') + d.nombre);
      button.onclick = async () => {
        if (!session.usuario) { location.href = 'login.html'; return; }
        button.disabled = true;
        try { await api(`disfraces/${d.id}/votos`, 'POST', {}); message(`¡Tu voto para ${d.nombre} ya cuenta!`); await gallery(); }
        catch (e) { message(e.message, true); button.disabled = false; }
      };
      actions.append(count, button); body.append(actions); card.append(image, body); grid.append(card);
    });
  } finally { grid.setAttribute('aria-busy', 'false'); }
}
function authForm() {
  $('#auth-form').onsubmit = async (event) => {
    event.preventDefault();
    if (page === 'registro' && $('#clave').value !== $('#confirmar').value) { message('Las contraseñas no coinciden.', true); return; }
    const button = event.target.querySelector('button'); button.disabled = true;
    try {
      const data = await api(page === 'login' ? 'login' : 'registro', 'POST', { nombre: $('#nombre').value, clave: $('#clave').value });
      if (page === 'login') location.href = data.usuario.rol === 'admin' ? 'admin.php' : 'index.html';
      else { event.target.reset(); message('Cuenta creada. Ya podés iniciar sesión con el enlace de abajo.'); }
    } catch (e) { message(e.message, true); }
    finally { button.disabled = false; }
  };
}
function resetEditor() { $('#costume-form').reset(); $('#costume-id').value = ''; $('#form-title').textContent = 'Nuevo disfraz'; $('#cancel-edit').hidden = true; }
async function adminList() {
  const { disfraces } = await api('disfraces'); const list = $('#admin-list'); list.replaceChildren();
  if (!disfraces.length) list.append(el('p', 'empty', 'Todavía no hay disfraces. Creá el primero.'));
  disfraces.forEach(d => {
    const row = el('article', 'admin-row'); row.append(el('h3', '', d.nombre), el('p', '', `${d.descripcion}\n${d.votos} votos`));
    const actions = el('div', 'row-actions'), edit = el('button', 'secondary', 'Editar'), remove = el('button', 'secondary danger', 'Eliminar');
    edit.onclick = () => {
      $('#costume-id').value = d.id; $('#nombre').value = d.nombre; $('#descripcion').value = d.descripcion; $('#foto').value = '';
      $('#form-title').textContent = 'Editar disfraz'; $('#cancel-edit').hidden = false; $('#nombre').focus();
    };
    remove.onclick = async () => {
      if (!confirm(`¿Eliminar «${d.nombre}» de la galería? Se conservará su historial de votos.`)) return;
      remove.disabled = true;
      try { await api(`disfraces/${d.id}`, 'DELETE'); if ($('#costume-id').value === String(d.id)) resetEditor(); message('Disfraz eliminado.'); await adminList(); }
      catch (e) { message(e.message, true); remove.disabled = false; }
    };
    actions.append(edit, remove); row.append(actions); list.append(row);
  });
}
function adminForm() {
  $('#cancel-edit').onclick = resetEditor;
  $('#costume-form').onsubmit = async (event) => {
    event.preventDefault(); const button = event.target.querySelector('[type=submit]'); button.disabled = true;
    try {
      const id = $('#costume-id').value;
      const data = await api(id ? `disfraces/${id}` : 'disfraces', id ? 'PUT' : 'POST', new FormData(event.target));
      resetEditor(); message(data.mensaje); await adminList();
    } catch (e) { message(e.message, true); }
    finally { button.disabled = false; }
  };
}
(async () => {
  try {
    session = await api('sesion'); navigation();
    if (page === 'home') await gallery();
    if (page === 'login' || page === 'registro') authForm();
    if (page === 'admin') { adminForm(); await adminList(); }
  } catch (e) { message(e.message, true); if ($('#stats')) $('#stats').textContent = 'No se pudo cargar el concurso'; }
})();
