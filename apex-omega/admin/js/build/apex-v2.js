import ke, { useState as hr, useEffect as Er } from "react";
import _r from "react-dom";
var Z = { exports: {} }, F = {};
/**
 * @license React
 * react-jsx-runtime.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Ce;
function gr() {
  if (Ce) return F;
  Ce = 1;
  var g = ke, E = Symbol.for("react.element"), j = Symbol.for("react.fragment"), y = Object.prototype.hasOwnProperty, $ = g.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner, W = { key: !0, ref: !0, __self: !0, __source: !0 };
  function x(b, f, S) {
    var p, _ = {}, m = null, Y = null;
    S !== void 0 && (m = "" + S), f.key !== void 0 && (m = "" + f.key), f.ref !== void 0 && (Y = f.ref);
    for (p in f) y.call(f, p) && !W.hasOwnProperty(p) && (_[p] = f[p]);
    if (b && b.defaultProps) for (p in f = b.defaultProps, f) _[p] === void 0 && (_[p] = f[p]);
    return { $$typeof: E, type: b, key: m, ref: Y, props: _, _owner: $.current };
  }
  return F.Fragment = j, F.jsx = x, F.jsxs = x, F;
}
var A = {};
/**
 * @license React
 * react-jsx-runtime.development.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Pe;
function yr() {
  return Pe || (Pe = 1, process.env.NODE_ENV !== "production" && function() {
    var g = ke, E = Symbol.for("react.element"), j = Symbol.for("react.portal"), y = Symbol.for("react.fragment"), $ = Symbol.for("react.strict_mode"), W = Symbol.for("react.profiler"), x = Symbol.for("react.provider"), b = Symbol.for("react.context"), f = Symbol.for("react.forward_ref"), S = Symbol.for("react.suspense"), p = Symbol.for("react.suspense_list"), _ = Symbol.for("react.memo"), m = Symbol.for("react.lazy"), Y = Symbol.for("react.offscreen"), ee = Symbol.iterator, De = "@@iterator";
    function Fe(e) {
      if (e === null || typeof e != "object")
        return null;
      var r = ee && e[ee] || e[De];
      return typeof r == "function" ? r : null;
    }
    var C = g.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
    function c(e) {
      {
        for (var r = arguments.length, t = new Array(r > 1 ? r - 1 : 0), n = 1; n < r; n++)
          t[n - 1] = arguments[n];
        Ae("error", e, t);
      }
    }
    function Ae(e, r, t) {
      {
        var n = C.ReactDebugCurrentFrame, o = n.getStackAddendum();
        o !== "" && (r += "%s", t = t.concat([o]));
        var u = t.map(function(i) {
          return String(i);
        });
        u.unshift("Warning: " + r), Function.prototype.apply.call(console[e], console, u);
      }
    }
    var Ie = !1, $e = !1, We = !1, Ye = !1, Le = !1, re;
    re = Symbol.for("react.module.reference");
    function Ne(e) {
      return !!(typeof e == "string" || typeof e == "function" || e === y || e === W || Le || e === $ || e === S || e === p || Ye || e === Y || Ie || $e || We || typeof e == "object" && e !== null && (e.$$typeof === m || e.$$typeof === _ || e.$$typeof === x || e.$$typeof === b || e.$$typeof === f || // This needs to include all possible module reference object
      // types supported by any Flight configuration anywhere since
      // we don't know which Flight build this will end up being used
      // with.
      e.$$typeof === re || e.getModuleId !== void 0));
    }
    function Ue(e, r, t) {
      var n = e.displayName;
      if (n)
        return n;
      var o = r.displayName || r.name || "";
      return o !== "" ? t + "(" + o + ")" : t;
    }
    function te(e) {
      return e.displayName || "Context";
    }
    function R(e) {
      if (e == null)
        return null;
      if (typeof e.tag == "number" && c("Received an unexpected object in getComponentNameFromType(). This is likely a bug in React. Please file an issue."), typeof e == "function")
        return e.displayName || e.name || null;
      if (typeof e == "string")
        return e;
      switch (e) {
        case y:
          return "Fragment";
        case j:
          return "Portal";
        case W:
          return "Profiler";
        case $:
          return "StrictMode";
        case S:
          return "Suspense";
        case p:
          return "SuspenseList";
      }
      if (typeof e == "object")
        switch (e.$$typeof) {
          case b:
            var r = e;
            return te(r) + ".Consumer";
          case x:
            var t = e;
            return te(t._context) + ".Provider";
          case f:
            return Ue(e, e.render, "ForwardRef");
          case _:
            var n = e.displayName || null;
            return n !== null ? n : R(e.type) || "Memo";
          case m: {
            var o = e, u = o._payload, i = o._init;
            try {
              return R(i(u));
            } catch {
              return null;
            }
          }
        }
      return null;
    }
    var T = Object.assign, k = 0, ne, ae, ie, oe, ue, se, le;
    function fe() {
    }
    fe.__reactDisabledLog = !0;
    function Ve() {
      {
        if (k === 0) {
          ne = console.log, ae = console.info, ie = console.warn, oe = console.error, ue = console.group, se = console.groupCollapsed, le = console.groupEnd;
          var e = {
            configurable: !0,
            enumerable: !0,
            value: fe,
            writable: !0
          };
          Object.defineProperties(console, {
            info: e,
            log: e,
            warn: e,
            error: e,
            group: e,
            groupCollapsed: e,
            groupEnd: e
          });
        }
        k++;
      }
    }
    function Me() {
      {
        if (k--, k === 0) {
          var e = {
            configurable: !0,
            enumerable: !0,
            writable: !0
          };
          Object.defineProperties(console, {
            log: T({}, e, {
              value: ne
            }),
            info: T({}, e, {
              value: ae
            }),
            warn: T({}, e, {
              value: ie
            }),
            error: T({}, e, {
              value: oe
            }),
            group: T({}, e, {
              value: ue
            }),
            groupCollapsed: T({}, e, {
              value: se
            }),
            groupEnd: T({}, e, {
              value: le
            })
          });
        }
        k < 0 && c("disabledDepth fell below zero. This is a bug in React. Please file an issue.");
      }
    }
    var B = C.ReactCurrentDispatcher, J;
    function L(e, r, t) {
      {
        if (J === void 0)
          try {
            throw Error();
          } catch (o) {
            var n = o.stack.trim().match(/\n( *(at )?)/);
            J = n && n[1] || "";
          }
        return `
` + J + e;
      }
    }
    var q = !1, N;
    {
      var Be = typeof WeakMap == "function" ? WeakMap : Map;
      N = new Be();
    }
    function ce(e, r) {
      if (!e || q)
        return "";
      {
        var t = N.get(e);
        if (t !== void 0)
          return t;
      }
      var n;
      q = !0;
      var o = Error.prepareStackTrace;
      Error.prepareStackTrace = void 0;
      var u;
      u = B.current, B.current = null, Ve();
      try {
        if (r) {
          var i = function() {
            throw Error();
          };
          if (Object.defineProperty(i.prototype, "props", {
            set: function() {
              throw Error();
            }
          }), typeof Reflect == "object" && Reflect.construct) {
            try {
              Reflect.construct(i, []);
            } catch (v) {
              n = v;
            }
            Reflect.construct(e, [], i);
          } else {
            try {
              i.call();
            } catch (v) {
              n = v;
            }
            e.call(i.prototype);
          }
        } else {
          try {
            throw Error();
          } catch (v) {
            n = v;
          }
          e();
        }
      } catch (v) {
        if (v && n && typeof v.stack == "string") {
          for (var a = v.stack.split(`
`), d = n.stack.split(`
`), s = a.length - 1, l = d.length - 1; s >= 1 && l >= 0 && a[s] !== d[l]; )
            l--;
          for (; s >= 1 && l >= 0; s--, l--)
            if (a[s] !== d[l]) {
              if (s !== 1 || l !== 1)
                do
                  if (s--, l--, l < 0 || a[s] !== d[l]) {
                    var h = `
` + a[s].replace(" at new ", " at ");
                    return e.displayName && h.includes("<anonymous>") && (h = h.replace("<anonymous>", e.displayName)), typeof e == "function" && N.set(e, h), h;
                  }
                while (s >= 1 && l >= 0);
              break;
            }
        }
      } finally {
        q = !1, B.current = u, Me(), Error.prepareStackTrace = o;
      }
      var w = e ? e.displayName || e.name : "", O = w ? L(w) : "";
      return typeof e == "function" && N.set(e, O), O;
    }
    function Je(e, r, t) {
      return ce(e, !1);
    }
    function qe(e) {
      var r = e.prototype;
      return !!(r && r.isReactComponent);
    }
    function U(e, r, t) {
      if (e == null)
        return "";
      if (typeof e == "function")
        return ce(e, qe(e));
      if (typeof e == "string")
        return L(e);
      switch (e) {
        case S:
          return L("Suspense");
        case p:
          return L("SuspenseList");
      }
      if (typeof e == "object")
        switch (e.$$typeof) {
          case f:
            return Je(e.render);
          case _:
            return U(e.type, r, t);
          case m: {
            var n = e, o = n._payload, u = n._init;
            try {
              return U(u(o), r, t);
            } catch {
            }
          }
        }
      return "";
    }
    var D = Object.prototype.hasOwnProperty, de = {}, ve = C.ReactDebugCurrentFrame;
    function V(e) {
      if (e) {
        var r = e._owner, t = U(e.type, e._source, r ? r.type : null);
        ve.setExtraStackFrame(t);
      } else
        ve.setExtraStackFrame(null);
    }
    function Ke(e, r, t, n, o) {
      {
        var u = Function.call.bind(D);
        for (var i in e)
          if (u(e, i)) {
            var a = void 0;
            try {
              if (typeof e[i] != "function") {
                var d = Error((n || "React class") + ": " + t + " type `" + i + "` is invalid; it must be a function, usually from the `prop-types` package, but received `" + typeof e[i] + "`.This often happens because of typos such as `PropTypes.function` instead of `PropTypes.func`.");
                throw d.name = "Invariant Violation", d;
              }
              a = e[i](r, i, n, t, null, "SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED");
            } catch (s) {
              a = s;
            }
            a && !(a instanceof Error) && (V(o), c("%s: type specification of %s `%s` is invalid; the type checker function must return `null` or an `Error` but returned a %s. You may have forgotten to pass an argument to the type checker creator (arrayOf, instanceOf, objectOf, oneOf, oneOfType, and shape all require an argument).", n || "React class", t, i, typeof a), V(null)), a instanceof Error && !(a.message in de) && (de[a.message] = !0, V(o), c("Failed %s type: %s", t, a.message), V(null));
          }
      }
    }
    var Ge = Array.isArray;
    function K(e) {
      return Ge(e);
    }
    function Xe(e) {
      {
        var r = typeof Symbol == "function" && Symbol.toStringTag, t = r && e[Symbol.toStringTag] || e.constructor.name || "Object";
        return t;
      }
    }
    function ze(e) {
      try {
        return pe(e), !1;
      } catch {
        return !0;
      }
    }
    function pe(e) {
      return "" + e;
    }
    function he(e) {
      if (ze(e))
        return c("The provided key is an unsupported type %s. This value must be coerced to a string before before using it here.", Xe(e)), pe(e);
    }
    var Ee = C.ReactCurrentOwner, He = {
      key: !0,
      ref: !0,
      __self: !0,
      __source: !0
    }, _e, ge;
    function Ze(e) {
      if (D.call(e, "ref")) {
        var r = Object.getOwnPropertyDescriptor(e, "ref").get;
        if (r && r.isReactWarning)
          return !1;
      }
      return e.ref !== void 0;
    }
    function Qe(e) {
      if (D.call(e, "key")) {
        var r = Object.getOwnPropertyDescriptor(e, "key").get;
        if (r && r.isReactWarning)
          return !1;
      }
      return e.key !== void 0;
    }
    function er(e, r) {
      typeof e.ref == "string" && Ee.current;
    }
    function rr(e, r) {
      {
        var t = function() {
          _e || (_e = !0, c("%s: `key` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://reactjs.org/link/special-props)", r));
        };
        t.isReactWarning = !0, Object.defineProperty(e, "key", {
          get: t,
          configurable: !0
        });
      }
    }
    function tr(e, r) {
      {
        var t = function() {
          ge || (ge = !0, c("%s: `ref` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://reactjs.org/link/special-props)", r));
        };
        t.isReactWarning = !0, Object.defineProperty(e, "ref", {
          get: t,
          configurable: !0
        });
      }
    }
    var nr = function(e, r, t, n, o, u, i) {
      var a = {
        // This tag allows us to uniquely identify this as a React Element
        $$typeof: E,
        // Built-in properties that belong on the element
        type: e,
        key: r,
        ref: t,
        props: i,
        // Record the component responsible for creating this element.
        _owner: u
      };
      return a._store = {}, Object.defineProperty(a._store, "validated", {
        configurable: !1,
        enumerable: !1,
        writable: !0,
        value: !1
      }), Object.defineProperty(a, "_self", {
        configurable: !1,
        enumerable: !1,
        writable: !1,
        value: n
      }), Object.defineProperty(a, "_source", {
        configurable: !1,
        enumerable: !1,
        writable: !1,
        value: o
      }), Object.freeze && (Object.freeze(a.props), Object.freeze(a)), a;
    };
    function ar(e, r, t, n, o) {
      {
        var u, i = {}, a = null, d = null;
        t !== void 0 && (he(t), a = "" + t), Qe(r) && (he(r.key), a = "" + r.key), Ze(r) && (d = r.ref, er(r, o));
        for (u in r)
          D.call(r, u) && !He.hasOwnProperty(u) && (i[u] = r[u]);
        if (e && e.defaultProps) {
          var s = e.defaultProps;
          for (u in s)
            i[u] === void 0 && (i[u] = s[u]);
        }
        if (a || d) {
          var l = typeof e == "function" ? e.displayName || e.name || "Unknown" : e;
          a && rr(i, l), d && tr(i, l);
        }
        return nr(e, a, d, o, n, Ee.current, i);
      }
    }
    var G = C.ReactCurrentOwner, ye = C.ReactDebugCurrentFrame;
    function P(e) {
      if (e) {
        var r = e._owner, t = U(e.type, e._source, r ? r.type : null);
        ye.setExtraStackFrame(t);
      } else
        ye.setExtraStackFrame(null);
    }
    var X;
    X = !1;
    function z(e) {
      return typeof e == "object" && e !== null && e.$$typeof === E;
    }
    function Re() {
      {
        if (G.current) {
          var e = R(G.current.type);
          if (e)
            return `

Check the render method of \`` + e + "`.";
        }
        return "";
      }
    }
    function ir(e) {
      return "";
    }
    var be = {};
    function or(e) {
      {
        var r = Re();
        if (!r) {
          var t = typeof e == "string" ? e : e.displayName || e.name;
          t && (r = `

Check the top-level render call using <` + t + ">.");
        }
        return r;
      }
    }
    function me(e, r) {
      {
        if (!e._store || e._store.validated || e.key != null)
          return;
        e._store.validated = !0;
        var t = or(r);
        if (be[t])
          return;
        be[t] = !0;
        var n = "";
        e && e._owner && e._owner !== G.current && (n = " It was passed a child from " + R(e._owner.type) + "."), P(e), c('Each child in a list should have a unique "key" prop.%s%s See https://reactjs.org/link/warning-keys for more information.', t, n), P(null);
      }
    }
    function Te(e, r) {
      {
        if (typeof e != "object")
          return;
        if (K(e))
          for (var t = 0; t < e.length; t++) {
            var n = e[t];
            z(n) && me(n, r);
          }
        else if (z(e))
          e._store && (e._store.validated = !0);
        else if (e) {
          var o = Fe(e);
          if (typeof o == "function" && o !== e.entries)
            for (var u = o.call(e), i; !(i = u.next()).done; )
              z(i.value) && me(i.value, r);
        }
      }
    }
    function ur(e) {
      {
        var r = e.type;
        if (r == null || typeof r == "string")
          return;
        var t;
        if (typeof r == "function")
          t = r.propTypes;
        else if (typeof r == "object" && (r.$$typeof === f || // Note: Memo only checks outer props here.
        // Inner props are checked in the reconciler.
        r.$$typeof === _))
          t = r.propTypes;
        else
          return;
        if (t) {
          var n = R(r);
          Ke(t, e.props, "prop", n, e);
        } else if (r.PropTypes !== void 0 && !X) {
          X = !0;
          var o = R(r);
          c("Component %s declared `PropTypes` instead of `propTypes`. Did you misspell the property assignment?", o || "Unknown");
        }
        typeof r.getDefaultProps == "function" && !r.getDefaultProps.isReactClassApproved && c("getDefaultProps is only used on classic React.createClass definitions. Use a static property named `defaultProps` instead.");
      }
    }
    function sr(e) {
      {
        for (var r = Object.keys(e.props), t = 0; t < r.length; t++) {
          var n = r[t];
          if (n !== "children" && n !== "key") {
            P(e), c("Invalid prop `%s` supplied to `React.Fragment`. React.Fragment can only have `key` and `children` props.", n), P(null);
            break;
          }
        }
        e.ref !== null && (P(e), c("Invalid attribute `ref` supplied to `React.Fragment`."), P(null));
      }
    }
    var Oe = {};
    function Se(e, r, t, n, o, u) {
      {
        var i = Ne(e);
        if (!i) {
          var a = "";
          (e === void 0 || typeof e == "object" && e !== null && Object.keys(e).length === 0) && (a += " You likely forgot to export your component from the file it's defined in, or you might have mixed up default and named imports.");
          var d = ir();
          d ? a += d : a += Re();
          var s;
          e === null ? s = "null" : K(e) ? s = "array" : e !== void 0 && e.$$typeof === E ? (s = "<" + (R(e.type) || "Unknown") + " />", a = " Did you accidentally export a JSX literal instead of a component?") : s = typeof e, c("React.jsx: type is invalid -- expected a string (for built-in components) or a class/function (for composite components) but got: %s.%s", s, a);
        }
        var l = ar(e, r, t, o, u);
        if (l == null)
          return l;
        if (i) {
          var h = r.children;
          if (h !== void 0)
            if (n)
              if (K(h)) {
                for (var w = 0; w < h.length; w++)
                  Te(h[w], e);
                Object.freeze && Object.freeze(h);
              } else
                c("React.jsx: Static children should always be an array. You are likely explicitly calling React.jsxs or React.jsxDEV. Use the Babel transform instead.");
            else
              Te(h, e);
        }
        if (D.call(r, "key")) {
          var O = R(e), v = Object.keys(r).filter(function(pr) {
            return pr !== "key";
          }), H = v.length > 0 ? "{key: someKey, " + v.join(": ..., ") + ": ...}" : "{key: someKey}";
          if (!Oe[O + H]) {
            var vr = v.length > 0 ? "{" + v.join(": ..., ") + ": ...}" : "{}";
            c(`A props object containing a "key" prop is being spread into JSX:
  let props = %s;
  <%s {...props} />
React keys must be passed directly to JSX without using spread:
  let props = %s;
  <%s key={someKey} {...props} />`, H, O, vr, O), Oe[O + H] = !0;
          }
        }
        return e === y ? sr(l) : ur(l), l;
      }
    }
    function lr(e, r, t) {
      return Se(e, r, t, !0);
    }
    function fr(e, r, t) {
      return Se(e, r, t, !1);
    }
    var cr = fr, dr = lr;
    A.Fragment = y, A.jsx = cr, A.jsxs = dr;
  }()), A;
}
process.env.NODE_ENV === "production" ? Z.exports = gr() : Z.exports = yr();
var I = Z.exports, Q, M = _r;
if (process.env.NODE_ENV === "production")
  Q = M.createRoot, M.hydrateRoot;
else {
  var we = M.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
  Q = function(g, E) {
    we.usingClientEntryPoint = !0;
    try {
      return M.createRoot(g, E);
    } finally {
      we.usingClientEntryPoint = !1;
    }
  };
}
function Rr({ api: g }) {
  const [E, j] = hr(null);
  return Er(() => {
    fetch(g + "/apex/v2/stats").then((y) => y.json()).then(j).catch(() => {
    });
  }, [g]), I.jsxs("div", { className: "apex-v2-dashboard", children: [I.jsx("h2", { children: "APEX Ω 2.0 — داشبورد زنده" }), I.jsx("pre", { children: JSON.stringify(E, null, 2) || "در حال بارگذاری..." }), I.jsx("div", { id: "apex-v2-chart", style: { height: 280, background: "#f5f3ff", borderRadius: 12, display: "grid", placeItems: "center" }, children: "Chart.js اینجا (timeseries)" })] });
}
const je = document.getElementById("apex-v2-root");
var xe;
je && Q(je).render(/* @__PURE__ */ I.jsx(Rr, { api: ((xe = window.ApexOmega) == null ? void 0 : xe.rest_url) || "/wp-json" }));
