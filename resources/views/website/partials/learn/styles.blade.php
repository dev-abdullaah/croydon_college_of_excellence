{{--
    Styles for the learning area: lesson reader, paper player, results.

    Scoped under `.lz-` and kept in one partial so the four learning pages
    share them without a second stylesheet request. Everything else comes
    from the existing rbt theme and Bootstrap utilities.
--}}
<style>
    .lz-card {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, .07);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
    }

    /* ---- Study cards -------------------------------------------------- */

    .lz-item {
        display: flex;
        gap: .9rem;
        padding: 1.1rem 0;
        border-bottom: 1px solid rgba(0, 0, 0, .06);
    }

    .lz-item:last-child {
        border-bottom: 0;
    }

    .lz-num {
        flex: 0 0 2.1rem;
        height: 2.1rem;
        border-radius: 50%;
        background: rgba(47, 87, 239, .1);
        color: #2f57ef;
        font-weight: 700;
        font-size: .82rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lz-item .lz-q {
        font-weight: 600;
        margin-bottom: .3rem;
    }

    .lz-item .lz-a::before {
        content: "A";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.4rem;
        height: 1.4rem;
        margin-right: .55rem;
        border-radius: 4px;
        background: #eef2ff;
        color: #2f57ef;
        font-size: .7rem;
        font-weight: 700;
        vertical-align: 2px;
    }

    /* ---- Quiz options ------------------------------------------------- */

    .lz-opt {
        display: flex;
        align-items: flex-start;
        gap: .8rem;
        width: 100%;
        text-align: left;
        padding: .9rem 1.1rem;
        margin-bottom: .7rem;
        border: 2px solid rgba(0, 0, 0, .09);
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
        transition: border-color .15s, background-color .15s;
    }

    .lz-opt:hover {
        border-color: rgba(47, 87, 239, .5);
    }

    .lz-opt input {
        margin-top: .25rem;
        flex: 0 0 auto;
    }

    .lz-key {
        flex: 0 0 1.8rem;
        height: 1.8rem;
        border-radius: 6px;
        background: #f1f3f7;
        color: #4a5163;
        font-weight: 700;
        font-size: .8rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lz-opt.is-picked {
        border-color: #2f57ef;
        background: rgba(47, 87, 239, .05);
    }

    .lz-opt.is-picked .lz-key {
        background: #2f57ef;
        color: #fff;
    }

    .lz-opt.is-right {
        border-color: #1f9254;
        background: rgba(31, 146, 84, .07);
    }

    .lz-opt.is-right .lz-key {
        background: #1f9254;
        color: #fff;
    }

    .lz-opt.is-wrong {
        border-color: #c92a2a;
        background: rgba(201, 42, 42, .06);
    }

    .lz-opt.is-wrong .lz-key {
        background: #c92a2a;
        color: #fff;
    }

    .lz-opt.is-muted {
        opacity: .62;
    }

    /* ---- Question jump grid ------------------------------------------- */

    .lz-jump {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
    }

    .lz-jump button {
        width: 2.3rem;
        height: 2.3rem;
        border-radius: 6px;
        border: 1px solid rgba(0, 0, 0, .12);
        background: #fff;
        font-size: .8rem;
        font-weight: 600;
        color: #4a5163;
    }

    .lz-jump button.done {
        background: rgba(31, 146, 84, .12);
        border-color: rgba(31, 146, 84, .35);
        color: #1f9254;
    }

    .lz-jump button.here {
        background: #2f57ef;
        border-color: #2f57ef;
        color: #fff;
    }

    /* ---- Result page -------------------------------------------------- */

    .lz-score {
        font-size: 3.4rem;
        font-weight: 800;
        line-height: 1;
    }

    .lz-mark {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-weight: 600;
    }

    .lz-mark-yes {
        color: #1f9254;
    }

    .lz-mark-no {
        color: #c92a2a;
    }

    .lz-explain {
        margin-top: .55rem;
        padding: .6rem .8rem;
        border-left: 3px solid rgba(0, 0, 0, .12);
        background: #f8f9fb;
        border-radius: 0 6px 6px 0;
        font-size: .9rem;
    }

    .active-dark-mode .lz-card,
    .active-dark-mode .lz-opt,
    .active-dark-mode .lz-jump button {
        background: #1b1d24;
        border-color: rgba(255, 255, 255, .12);
        color: #e8eaf0;
    }

    .active-dark-mode .lz-explain {
        background: #23262f;
        border-left-color: rgba(255, 255, 255, .2);
    }
</style>
