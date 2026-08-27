<<<<<<<< HEAD:public/build/assets/use-permissions-BSoKp_HT.js
import{C as e}from"./app-DF07Ufdh.js";function t(){let{auth:t}=e().props,n=t?.permissions||[],r=t?.roles||[];return{permissions:n,roles:r,can:e=>Array.isArray(e)?e.some(e=>n.includes(e)):n.includes(e),canAll:e=>e.every(e=>n.includes(e)),hasRole:e=>Array.isArray(e)?e.some(e=>r.includes(e)):r.includes(e),hasAllRoles:e=>e.every(e=>r.includes(e))}}export{t};
========
import{C as e}from"./app-CF44PoX5.js";function t(){let{auth:t}=e().props,n=t?.permissions||[],r=t?.roles||[];return{permissions:n,roles:r,can:e=>Array.isArray(e)?e.some(e=>n.includes(e)):n.includes(e),canAll:e=>e.every(e=>n.includes(e)),hasRole:e=>Array.isArray(e)?e.some(e=>r.includes(e)):r.includes(e),hasAllRoles:e=>e.every(e=>r.includes(e))}}export{t};
>>>>>>>> navix:public/build/assets/use-permissions-CO6cY9FZ.js
