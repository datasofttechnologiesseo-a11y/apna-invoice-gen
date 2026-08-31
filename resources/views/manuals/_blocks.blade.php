{{-- Renders one content block from config/handbook.php. Shared by the full
     handbook and any future manual, so the block vocabulary stays in one
     place. dompdf has no flexbox or grid, so everything here is tables and
     plain block elements. --}}
@foreach ($blocks as $block)
    @switch($block['type'])

        @case('p')
            <p>{{ $block['text'] }}</p>
            @break

        @case('list')
            <ul>
                @foreach ($block['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            @break

        @case('steps')
            <table class="steps">
                @foreach ($block['items'] as $i => $item)
                    <tr>
                        <td class="step-n">{{ $i + 1 }}</td>
                        <td class="step-t">{{ $item }}</td>
                    </tr>
                @endforeach
            </table>
            @break

        @case('note')
            <table class="note">
                <tr>
                    <td>
                        <div class="note-label">{{ $block['label'] }}</div>
                        <div>{{ $block['text'] }}</div>
                    </td>
                </tr>
            </table>
            @break

        @case('table')
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($block['head'] as $th)
                            <th>{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($block['rows'] as $row)
                        <tr>
                            @foreach ($row as $td)
                                <td>{{ $td }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @break

    @endswitch
@endforeach
