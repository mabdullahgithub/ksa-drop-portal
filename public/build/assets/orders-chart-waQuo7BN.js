import{t as e}from"./jsx-runtime-C7oxC63R.js";import{J as t,a as n,i as r,r as i,t as a,u as o,v as s}from"./AreaChart-CDdvMsXf.js";var c=e();function l({data:e}){return(0,c.jsxs)(`div`,{className:`orders-chart`,children:[(0,c.jsx)(`style`,{children:`
                .orders-chart {
                    --series-1: #2a78d6;
                    --chart-surface: #fcfcfb;
                    --chart-grid: #e1e0d9;
                    --chart-axis-ink: #898781;
                    --chart-ink: #0b0b0b;
                    --chart-ink-2: #52514e;
                    height: 240px;
                }
                @media (prefers-color-scheme: dark) {
                    .orders-chart {
                        --series-1: #3987e5;
                        --chart-surface: #1a1a19;
                        --chart-grid: #2c2c2a;
                        --chart-ink: #ffffff;
                        --chart-ink-2: #c3c2b7;
                    }
                }
                .orders-chart-tooltip {
                    background: var(--chart-surface);
                    border: 1px solid var(--chart-grid);
                    border-radius: 8px;
                    padding: 8px 10px;
                    font-size: 12px;
                    color: var(--chart-ink-2);
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                }
                .orders-chart-tooltip strong {
                    display: block;
                    color: var(--chart-ink);
                    font-size: 14px;
                }
            `}),(0,c.jsx)(t,{width:`100%`,height:`100%`,children:(0,c.jsxs)(a,{data:e,margin:{top:8,right:8,bottom:0,left:-16},children:[(0,c.jsx)(o,{stroke:`var(--chart-grid)`,strokeWidth:1,vertical:!1}),(0,c.jsx)(r,{dataKey:`date`,tickFormatter:u,tick:{fill:`var(--chart-axis-ink)`,fontSize:11},tickLine:!1,axisLine:{stroke:`var(--chart-grid)`},interval:`preserveStartEnd`,minTickGap:32}),(0,c.jsx)(i,{allowDecimals:!1,tick:{fill:`var(--chart-axis-ink)`,fontSize:11},tickLine:!1,axisLine:!1,width:40}),(0,c.jsx)(s,{cursor:{stroke:`var(--chart-axis-ink)`,strokeWidth:1},content:({active:e,payload:t})=>{if(!e||!t?.length)return null;let n=t[0].payload;return(0,c.jsxs)(`div`,{className:`orders-chart-tooltip`,children:[(0,c.jsxs)(`strong`,{children:[n.orders,` `,n.orders===1?`order`:`orders`]}),d(n.date)]})}}),(0,c.jsx)(n,{type:`monotone`,dataKey:`orders`,stroke:`var(--series-1)`,strokeWidth:2,strokeLinejoin:`round`,strokeLinecap:`round`,fill:`var(--series-1)`,fillOpacity:.1,activeDot:{r:4,fill:`var(--series-1)`,stroke:`var(--chart-surface)`,strokeWidth:2}})]})})]})}function u(e){return new Date(`${e}T00:00:00`).toLocaleDateString(void 0,{month:`short`,day:`numeric`})}function d(e){return new Date(`${e}T00:00:00`).toLocaleDateString(void 0,{weekday:`short`,month:`short`,day:`numeric`})}export{l as OrdersChart};